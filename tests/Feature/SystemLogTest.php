<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Verify how failed requests reach the system log.
 *
 * Client failures are reported through a callback in bootstrap/app.php that writes one compact
 * warning and suppresses the default report; server faults fall through to the default reporter
 * and keep their stack trace. These tests pin both halves, plus the daily file naming.
 */
class SystemLogTest extends TestCase
{
    use RefreshDatabase;

    private string $logDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'clinic-log-'.uniqid();

        Route::get('/api/_test/ok', fn (): array => ['success' => true]);
        Route::get('/api/_test/boom', function (): never {
            throw new RuntimeException('Something broke');
        });
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->logDirectory);

        parent::tearDown();
    }

    /**
     * A rejected payload produces one warning carrying the request that caused it.
     */
    public function test_validation_failure_is_logged_as_a_warning_with_request_context(): void
    {
        Log::spy();

        $this->postJson('/api/login', [])->assertStatus(422);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message !== ''
                    && $context['status'] === 422
                    && $context['type'] === 'ValidationException'
                    && $context['method'] === 'POST'
                    && $context['url'] === url('/api/login')
                    && $context['user_id'] === null
                    && array_key_exists('email', $context['errors']);
            });

        Log::shouldNotHaveReceived('error');
    }

    /**
     * The compact warning must not carry the exception object, which is what drags a full stack
     * trace into the file for what is only a client mistake.
     */
    public function test_client_error_warning_carries_no_exception_payload(): void
    {
        Log::spy();

        $this->postJson('/api/login', [])->assertStatus(422);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => ! array_key_exists('exception', $context));
    }

    /**
     * A missing token is a client failure too, and used to vanish from the log entirely.
     */
    public function test_unauthenticated_request_is_logged_as_a_warning(): void
    {
        Log::spy();

        $this->getJson('/api/me')->assertUnauthorized();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context['status'] === 401
                && $context['type'] === 'AuthenticationException'
                && $context['user_id'] === null);
    }

    /**
     * An unmatched route reports the status Symfony resolved rather than a hardcoded one.
     */
    public function test_unknown_route_is_logged_as_a_warning(): void
    {
        Log::spy();

        $this->getJson('/api/_test/does-not-exist')->assertNotFound();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context['status'] === 404);
    }

    /**
     * A server fault keeps the default report, so the stack trace survives.
     */
    public function test_server_fault_is_logged_as_an_error_with_its_exception(): void
    {
        Log::spy();

        $this->getJson('/api/_test/boom')->assertStatus(500);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Something broke'
                    && $context['exception'] instanceof RuntimeException
                    && $context['method'] === 'GET'
                    && $context['url'] === url('/api/_test/boom');
            });

        Log::shouldNotHaveReceived('warning');
    }

    /**
     * Successful traffic stays out of the log; only failures are recorded.
     */
    public function test_successful_request_is_not_logged(): void
    {
        Log::spy();

        $this->getJson('/api/_test/ok')->assertOk();

        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    /**
     * The daily channel writes one file per day, named by Monolog's own convention.
     */
    public function test_daily_channel_writes_a_file_named_by_date(): void
    {
        $this->useTemporaryDailyChannel();

        $this->postJson('/api/login', [])->assertStatus(422);

        $contents = File::get($this->todaysLogFile());

        $this->assertStringContainsString(config('app.env').'.WARNING', $contents);
        $this->assertStringContainsString('"status":422', $contents);
        $this->assertStringNotContainsString('[stacktrace]', $contents);
    }

    /**
     * The counterpart of the test above: a server fault does reach the file with its trace, which
     * is the reason client errors are the only ones routed around the default reporter.
     */
    public function test_server_fault_reaches_the_file_with_its_stack_trace(): void
    {
        $this->useTemporaryDailyChannel();

        $this->getJson('/api/_test/boom')->assertStatus(500);

        $contents = File::get($this->todaysLogFile());

        $this->assertStringContainsString(config('app.env').'.ERROR', $contents);
        $this->assertStringContainsString('Something broke', $contents);
        $this->assertStringContainsString('[stacktrace]', $contents);
    }

    /**
     * Entries below the configured level never reach the file, which is what keeps the log to
     * warnings and errors only.
     */
    public function test_entries_below_warning_are_discarded(): void
    {
        $this->useTemporaryDailyChannel();

        Log::info('Routine chatter that must not be written');

        $this->assertFileDoesNotExist($this->todaysLogFile());
    }

    /**
     * Point the daily channel at a throwaway directory so a run never touches storage/logs.
     */
    private function useTemporaryDailyChannel(): void
    {
        config([
            'logging.default' => 'daily',
            'logging.channels.daily.path' => $this->logDirectory.DIRECTORY_SEPARATOR.'laravel.log',
            'logging.channels.daily.level' => 'warning',
        ]);
    }

    private function todaysLogFile(): string
    {
        return $this->logDirectory.DIRECTORY_SEPARATOR.'laravel-'.now()->format('Y-m-d').'.log';
    }
}
