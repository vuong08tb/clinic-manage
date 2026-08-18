<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verify administrator-only user management and final-admin protection.
 */
class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by user management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    public function test_admin_can_create_user_with_an_assigned_role(): void
    {
        $admin = $this->createUser('ADMIN');
        $receptionistRole = $this->role('RECEPTIONIST');
        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'New Receptionist',
            'email' => 'new.receptionist@clinic.test',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role_id' => $receptionistRole->id,
        ])->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'User created',
                'data' => [
                    'name' => 'New Receptionist',
                    'email' => 'new.receptionist@clinic.test',
                    'is_active' => true,
                    'role' => ['name' => 'RECEPTIONIST'],
                ],
            ])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonMissingPath('data.permissions');

        $created = User::query()->where('email', 'new.receptionist@clinic.test')->firstOrFail();

        $this->assertSame($receptionistRole->id, $created->role_id);
        $this->assertTrue(password_verify('Password@123', $created->password));
    }

    public function test_store_validation_returns_422_errors_by_field(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));
        $existing = $this->createUser('RECEPTIONIST', ['email' => 'existing@clinic.test']);

        $this->postJson('/api/users', [
            'name' => 'Duplicate User',
            'email' => $existing->email,
            'password' => 'Password@123',
            'password_confirmation' => 'different-password',
            'role_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'message',
                'errors' => ['email', 'password', 'role_id'],
            ])
            ->assertJsonMissingPath('data');
    }

    public function test_admin_can_list_filter_search_and_paginate_users(): void
    {
        $admin = $this->createUser('ADMIN', ['name' => 'Primary Admin']);
        $receptionistRole = $this->role('RECEPTIONIST');
        $this->createUser('RECEPTIONIST', [
            'name' => 'Alice Reception',
            'email' => 'alice@clinic.test',
            'is_active' => true,
        ]);
        $this->createUser('RECEPTIONIST', [
            'name' => 'Inactive Reception',
            'email' => 'inactive@clinic.test',
            'is_active' => false,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/users?role_id={$receptionistRole->id}&is_active=1&q=alice&per_page=1")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Users retrieved',
                'data' => [
                    ['email' => 'alice@clinic.test'],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 1,
                    'total' => 1,
                    'last_page' => 1,
                ],
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('links')
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_admin_can_show_and_update_a_user(): void
    {
        $admin = $this->createUser('ADMIN');
        $user = $this->createUser('RECEPTIONIST');
        $doctorRole = $this->role('DOCTOR');
        Sanctum::actingAs($admin);

        $this->getJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->patchJson("/api/users/{$user->id}", [
            'name' => 'Updated Doctor',
            'email' => $user->email,
            'password' => 'Updated@123',
            'password_confirmation' => 'Updated@123',
            'role_id' => $doctorRole->id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Doctor')
            ->assertJsonPath('data.role.name', 'DOCTOR')
            ->assertJsonPath('data.is_active', true);

        $user->refresh();
        $this->assertSame($doctorRole->id, $user->role_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue(password_verify('Updated@123', $user->password));
    }

    public function test_profile_update_cannot_bypass_the_status_endpoint(): void
    {
        $admin = $this->createUser('ADMIN');
        $user = $this->createUser('RECEPTIONIST');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$user->id}", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['is_active']]);

        $this->assertTrue($user->refresh()->is_active);
    }

    public function test_empty_update_payload_returns_422(): void
    {
        $admin = $this->createUser('ADMIN');
        $user = $this->createUser('RECEPTIONIST');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$user->id}", [])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['user']]);
    }

    public function test_destroy_deactivates_without_deleting_the_user(): void
    {
        $admin = $this->createUser('ADMIN');
        $user = $this->createUser('RECEPTIONIST');
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User deactivated')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);
    }

    public function test_status_endpoint_can_deactivate_and_reactivate_a_user(): void
    {
        $admin = $this->createUser('ADMIN');
        $user = $this->createUser('RECEPTIONIST');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$user->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->patchJson("/api/users/{$user->id}/status", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
    }

    public function test_non_admin_roles_cannot_manage_users(): void
    {
        $receptionist = $this->createUser('RECEPTIONIST');
        $target = $this->createUser('DOCTOR');
        Sanctum::actingAs($receptionist);

        $this->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: USERS.FINDALL');

        $this->postJson('/api/users', [])
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: USERS.CREATE');

        $this->getJson("/api/users/{$target->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: USERS.FINDONE');

        $this->patchJson("/api/users/{$target->id}", ['name' => 'Forbidden Update'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: USERS.UPDATE');

        $this->deleteJson("/api/users/{$target->id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: USERS.DELETE');

        $this->patchJson("/api/users/{$target->id}/status", ['is_active' => false])
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: USERS.UPDATESTATUS');
    }

    public function test_user_management_requires_authentication(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_missing_user_returns_404_json(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/users/999999')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => [],
            ]);
    }

    public function test_last_active_admin_cannot_be_assigned_another_role(): void
    {
        $admin = $this->createUser('ADMIN');
        $receptionistRole = $this->role('RECEPTIONIST');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$admin->id}", [
            'role_id' => $receptionistRole->id,
        ])->assertUnprocessable()
            ->assertJsonPath(
                'errors.role_id.0',
                'The last active administrator cannot be assigned another role.',
            );

        $this->assertSame('ADMIN', $admin->refresh()->role->name);
    }

    public function test_last_active_admin_cannot_be_deactivated_through_status(): void
    {
        $admin = $this->createUser('ADMIN');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$admin->id}/status", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.is_active.0',
                'The last active administrator cannot be deactivated.',
            );

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_last_active_admin_cannot_be_deactivated_through_destroy(): void
    {
        $admin = $this->createUser('ADMIN');
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/users/{$admin->id}")
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['is_active']]);

        $this->assertTrue($admin->refresh()->is_active);
    }

    public function test_inactive_admin_does_not_count_as_an_active_admin_replacement(): void
    {
        $activeAdmin = $this->createUser('ADMIN');
        $this->createUser('ADMIN', ['is_active' => false]);
        Sanctum::actingAs($activeAdmin);

        $this->patchJson("/api/users/{$activeAdmin->id}/status", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['is_active']]);

        $this->assertTrue($activeAdmin->refresh()->is_active);
    }

    public function test_one_of_two_active_admins_can_change_role_or_be_deactivated(): void
    {
        $firstAdmin = $this->createUser('ADMIN');
        $secondAdmin = $this->createUser('ADMIN');
        $receptionistRole = $this->role('RECEPTIONIST');
        Sanctum::actingAs($firstAdmin);

        $this->patchJson("/api/users/{$secondAdmin->id}", [
            'role_id' => $receptionistRole->id,
        ])->assertOk()
            ->assertJsonPath('data.role.name', 'RECEPTIONIST');

        $replacementAdmin = $this->createUser('ADMIN');

        $this->patchJson("/api/users/{$replacementAdmin->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_deactivate_self_when_another_active_admin_remains(): void
    {
        $firstAdmin = $this->createUser('ADMIN');
        $this->createUser('ADMIN');
        Sanctum::actingAs($firstAdmin);

        $this->patchJson("/api/users/{$firstAdmin->id}/status", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($firstAdmin->refresh()->is_active);
    }

    public function test_deactivation_revokes_all_tokens_and_reactivation_requires_login_again(): void
    {
        $admin = $this->createUser('ADMIN');
        $user = $this->createUser('RECEPTIONIST');
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $userToken = $user->createToken('user')->plainTextToken;

        $this->withToken($adminToken)
            ->patchJson("/api/users/{$user->id}/status", ['is_active' => false])
            ->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
        ]);

        $this->app['auth']->forgetGuards();

        $this->withToken($userToken)
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        $this->withToken($adminToken)
            ->patchJson("/api/users/{$user->id}/status", ['is_active' => true])
            ->assertOk();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
    }

    /**
     * Create an active user assigned to the requested seeded role.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(string $roleName, array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'role_id' => $this->role($roleName)->id,
            'name' => "{$roleName} User",
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Return a seeded role by its stable catalog name.
     */
    private function role(string $name): Role
    {
        return Role::query()->where('name', $name)->firstOrFail();
    }
}
