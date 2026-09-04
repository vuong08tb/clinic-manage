<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cover the three throttling layers guarding the authenticated API.
 *
 * Every authenticated route carries 'api', a broad ceiling keyed by user id. Two routes carry a
 * second, tighter limiter on top: 'sensitive' for the aggregate query behind /api/stats, and
 * 'payment' for the endpoints that spend an outbound PayPal call. Stacking them means both must
 * agree before a request proceeds, so the tests below pin the tighter limit where one applies
 * and pin the shared ceiling everywhere else.
 */
class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requests allowed per minute for one user across the authenticated API.
     */
    private const API_LIMIT = 120;

    /**
     * Requests allowed per minute for one user against the statistics endpoint.
     */
    private const SENSITIVE_LIMIT = 20;

    /**
     * Requests allowed per minute for one user against the PayPal endpoints.
     */
    private const PAYMENT_LIMIT = 10;

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
     * The shared ceiling rejects the request that follows the last allowed one.
     */
    public function test_the_shared_limiter_blocks_a_user_past_the_general_limit(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        for ($attempt = 1; $attempt <= self::API_LIMIT; $attempt++) {
            $this->getJson('/api/me')->assertOk();
        }

        $this->getJson('/api/me')->assertTooManyRequests();
    }

    /**
     * The key is the user id, so one heavy user cannot spend a colleague's quota.
     *
     * Both users below are seen from the same address, which is what a clinic behind one NAT
     * gateway looks like. Keying by address instead would fail this test, and in production it
     * would let whoever works fastest lock out everyone sharing the office connection.
     */
    public function test_exhausting_one_user_leaves_another_user_unaffected(): void
    {
        $heavy = $this->createUser('ADMIN');
        $colleague = $this->createUser('ADMIN');

        Sanctum::actingAs($heavy);
        $this->exhaust('/api/me', self::API_LIMIT);
        $this->getJson('/api/me')->assertTooManyRequests();

        Sanctum::actingAs($colleague);
        $this->getJson('/api/me')->assertOk();
    }

    /**
     * Where two limiters stack, the tighter one decides.
     *
     * /api/stats carries both 'api' (120) and 'sensitive' (20). Passing this test at 20 proves
     * the second limiter is reached at all; without it the route would silently inherit 120.
     */
    public function test_statistics_are_capped_by_the_tighter_sensitive_limiter(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        for ($attempt = 1; $attempt <= self::SENSITIVE_LIMIT; $attempt++) {
            $this->getJson('/api/stats')->assertOk();
        }

        $this->getJson('/api/stats')->assertTooManyRequests();
    }

    /**
     * A stacked route reports the tighter allowance to the client, not the looser one.
     *
     * Both limiters write rate-limit headers on the way out. Reporting 120 on a route that stops
     * at 20 would tell a client it has five times the budget it really has.
     */
    public function test_a_stacked_route_reports_the_tighter_limit_in_its_headers(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', (string) self::SENSITIVE_LIMIT)
            ->assertHeader('X-RateLimit-Remaining', (string) (self::SENSITIVE_LIMIT - 1));

        $this->getJson('/api/me')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', (string) self::API_LIMIT);
    }

    /**
     * The PayPal endpoints stop well below the shared ceiling.
     *
     * Each allowed call spends an outbound request against the merchant account's own quota at
     * PayPal, which the whole practice shares, so this limit protects a resource that lives
     * outside the application entirely.
     */
    public function test_paypal_endpoints_are_capped_below_the_general_limit(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response([
                'access_token' => 'FAKE_JWT_CLIENT_TOKEN',
            ], 200),
        ]);

        Sanctum::actingAs($this->createUser('CASHIER'));

        for ($attempt = 1; $attempt <= self::PAYMENT_LIMIT; $attempt++) {
            $this->getJson('/api/payments/paypal/client-token')->assertOk();
        }

        $this->getJson('/api/payments/paypal/client-token')->assertTooManyRequests();
    }

    /**
     * A throttled response must keep the API envelope and tell the client how long to wait.
     *
     * The API renders its own body for every HTTP exception, so these headers only survive
     * because that renderer copies them off the exception. The client reads Retry-After to
     * phrase its message, so losing it here degrades the interface without failing anything.
     */
    public function test_a_throttled_response_carries_the_envelope_and_retry_headers(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->exhaust('/api/stats', self::SENSITIVE_LIMIT);

        $this->getJson('/api/stats')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', (string) self::SENSITIVE_LIMIT)
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertJsonPath('success', false);
    }

    /**
     * Negative control: ordinary traffic is answered normally.
     *
     * Without this, a limiter that rejected every request would still pass every test above.
     */
    public function test_traffic_below_the_limit_is_answered_normally(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        for ($attempt = 1; $attempt <= self::SENSITIVE_LIMIT - 1; $attempt++) {
            $this->getJson('/api/stats')
                ->assertOk()
                ->assertJsonPath('success', true);
        }

        $this->getJson('/api/patients')->assertOk();
    }

    /**
     * Spend every allowed request against the given path.
     */
    private function exhaust(string $path, int $limit): void
    {
        for ($attempt = 1; $attempt <= $limit; $attempt++) {
            $this->getJson($path)->assertOk();
        }
    }

    /**
     * Create an account holding the given role.
     */
    private function createUser(string $role): User
    {
        return User::factory()
            ->for(Role::query()->where('name', $role)->firstOrFail())
            ->create();
    }
}
