<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verify the complete Sanctum login, logout, and current-user API flow.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the RBAC catalog required by every authentication scenario.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * An active account receives a Bearer token and its RBAC context.
     */
    public function test_active_user_can_login_with_bearer_token(): void
    {
        $user = $this->createAdmin();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Logged in',
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'is_active' => true,
                        'role' => ['name' => 'ADMIN'],
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['permissions'],
                ],
            ])
            ->assertJsonMissingPath('data.data');

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /**
     * An incorrect password must not issue a token or disclose account details.
     */
    public function test_wrong_password_returns_generic_unauthorized_response(): void
    {
        $user = $this->createAdmin();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Invalid credentials',
                'errors' => [],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * An unknown email uses the same response as every other credential failure.
     */
    public function test_unknown_email_returns_same_unauthorized_response(): void
    {
        $this->postJson('/api/login', [
            'email' => 'missing@clinic.test',
            'password' => 'password',
        ])->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Invalid credentials',
                'errors' => [],
            ]);
    }

    /**
     * A disabled account cannot receive a new token even with a valid password.
     */
    public function test_inactive_user_cannot_login(): void
    {
        $user = $this->createAdmin(false);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid credentials');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Invalid login input returns field-level errors in the API envelope.
     */
    public function test_login_validation_errors_use_api_envelope(): void
    {
        $this->postJson('/api/login', [
            'email' => 'not-an-email',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'message',
                'errors' => ['email', 'password'],
            ]);
    }

    /**
     * The current-user endpoint requires a token and returns role permissions.
     */
    public function test_me_requires_a_valid_token_and_returns_rbac_context(): void
    {
        // Deliberately omit the Accept header to verify API guests are never redirected.
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => [],
            ]);

        $user = $this->createAdmin();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Current user retrieved',
                'data' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => ['name' => 'ADMIN'],
                ],
            ])
            ->assertJsonStructure([
                'data' => ['permissions'],
            ]);
    }

    /**
     * Logout revokes the current token without ending other active sessions.
     */
    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = $this->createAdmin();
        $currentToken = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withToken($currentToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Logged out',
                'data' => null,
            ]);

        // Feature tests reuse the application instance, so clear the cached authenticated user.
        $this->app['auth']->forgetGuards();

        $this->withToken($currentToken)
            ->getJson('/api/me')
            ->assertUnauthorized();

        // Force Sanctum to authenticate the next request from the remaining token.
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)
            ->getJson('/api/me')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    /**
     * Public self-registration remains outside the supported API surface.
     */
    public function test_public_registration_route_does_not_exist(): void
    {
        $this->postJson('/api/register')->assertNotFound();
    }

    /**
     * Create an administrator account for authentication scenarios.
     */
    private function createAdmin(bool $isActive = true): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('name', 'ADMIN')->value('id'),
            'name' => 'Clinic Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => $isActive,
        ]);
    }
}
