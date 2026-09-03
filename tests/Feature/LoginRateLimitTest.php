<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Cover the two throttling layers guarding POST /api/login.
 *
 * The first layer allows five attempts per minute for an (email, IP) pair, which slows password
 * guessing against one account without letting a stranger lock that account out from elsewhere.
 * The second layer allows twenty attempts per minute for an IP, which catches a single source
 * spraying one common password across many accounts. Each test below pins one of those claims.
 */
class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Attempts allowed per minute for one email address seen from one IP address.
     */
    private const PER_ACCOUNT_LIMIT = 5;

    /**
     * Attempts allowed per minute for one IP address across every account it tries.
     */
    private const PER_ADDRESS_LIMIT = 20;

    /**
     * Seed the RBAC catalog and start every scenario with an empty attempt counter.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);

        // The limiter keeps its counters in the cache store. Tests run on the array driver, which
        // is already empty per test, but flushing keeps the suite honest if that driver changes.
        Cache::flush();
    }

    /**
     * The first layer stops password guessing against a single account.
     */
    public function test_login_is_blocked_after_five_failed_attempts_on_one_account(): void
    {
        $user = $this->createAdmin();

        for ($attempt = 1; $attempt <= self::PER_ACCOUNT_LIMIT; $attempt++) {
            $this->attemptLogin($user->email)->assertUnauthorized();
        }

        $this->attemptLogin($user->email)->assertTooManyRequests();
    }

    /**
     * A throttled response must tell the client how long to wait.
     *
     * The API renders its own envelope for every HTTP exception, so these headers only survive
     * because that renderer copies them off the exception. Deleting that copy breaks retries
     * silently, which is exactly what this assertion is here to prevent.
     */
    public function test_throttled_response_carries_the_retry_headers(): void
    {
        $user = $this->createAdmin();

        $this->exhaustAccountLimit($user->email);

        $this->attemptLogin($user->email)
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', (string) self::PER_ACCOUNT_LIMIT)
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertJsonPath('success', false);
    }

    /**
     * The limiter key is lowercased, so capitalisation cannot buy a fresh set of attempts.
     *
     * The users table collates case-insensitively, meaning every spelling below resolves to the
     * same account. A key that did not normalise would hand an attacker one counter per spelling.
     */
    public function test_changing_letter_case_does_not_reset_the_attempt_counter(): void
    {
        $user = $this->createAdmin();

        $this->exhaustAccountLimit($user->email);

        $this->attemptLogin(Str::upper($user->email))->assertTooManyRequests();
        $this->attemptLogin(Str::ucfirst($user->email))->assertTooManyRequests();
    }

    /**
     * The second layer stops one source from spraying a password across many accounts.
     *
     * Every address below is distinct, so the per-account counter never reaches its limit. Only
     * the per-address layer can reject this traffic.
     */
    public function test_spraying_many_accounts_from_one_address_hits_the_second_layer(): void
    {
        for ($attempt = 1; $attempt <= self::PER_ADDRESS_LIMIT; $attempt++) {
            $this->attemptLogin("victim{$attempt}@clinic.test", 'Password123')
                ->assertUnauthorized();
        }

        $this->attemptLogin('one-more@clinic.test', 'Password123')
            ->assertTooManyRequests();
    }

    /**
     * Exhausting an account from one address must not lock its owner out from another.
     *
     * This is what keeps the limiter from becoming a denial-of-service tool: the key pairs the
     * email with the caller's IP, so a stranger can only ever spend their own quota.
     */
    public function test_a_lockout_at_one_address_does_not_block_the_owner_elsewhere(): void
    {
        $user = $this->createAdmin();

        $this->fromAddress('203.0.113.9');
        $this->exhaustAccountLimit($user->email);
        $this->attemptLogin($user->email)->assertTooManyRequests();

        $this->fromAddress('198.51.100.7');
        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
    }

    /**
     * Negative control: traffic below the limit still receives its ordinary answer.
     *
     * Without this, a limiter that rejected everything would pass every other test in this file.
     */
    public function test_attempts_below_the_limit_are_answered_as_unauthorized(): void
    {
        $user = $this->createAdmin();

        for ($attempt = 1; $attempt <= self::PER_ACCOUNT_LIMIT - 1; $attempt++) {
            $this->attemptLogin($user->email)
                ->assertUnauthorized()
                ->assertJsonPath('message', 'Invalid credentials');
        }
    }

    /**
     * A non-string email must fail validation rather than break the limiter.
     *
     * The limiter runs before LoginRequest, so it reads unvalidated input. Building the key from
     * that value without a type guard turned this request into a 500 that was never counted,
     * which handed an unauthenticated caller an unlimited supply of logged stack traces.
     */
    public function test_a_non_string_email_is_rejected_by_validation_not_by_a_server_error(): void
    {
        $this->postJson('/api/login', [
            'email' => ['first@clinic.test', 'second@clinic.test'],
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    /**
     * Send one failing login attempt for the given address.
     */
    private function attemptLogin(string $email, string $password = 'wrong-password'): TestResponse
    {
        return $this->postJson('/api/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    /**
     * Spend every per-account attempt so the next call is throttled.
     */
    private function exhaustAccountLimit(string $email): void
    {
        for ($attempt = 1; $attempt <= self::PER_ACCOUNT_LIMIT; $attempt++) {
            $this->attemptLogin($email)->assertUnauthorized();
        }
    }

    /**
     * Send the following requests from the given client address.
     */
    private function fromAddress(string $ip): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    /**
     * Create an administrator account for authentication scenarios.
     */
    private function createAdmin(): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('name', 'ADMIN')->value('id'),
            'name' => 'Clinic Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
