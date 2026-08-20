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
 * Verify the role catalog endpoint and its RBAC access.
 */
class RoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the role and permission catalog required by role management.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
    }

    /**
     * Verify an administrator can list every seeded role.
     */
    public function test_admin_can_list_roles(): void
    {
        Sanctum::actingAs($this->createUser('ADMIN'));

        $this->getJson('/api/roles')
            ->assertOk()
            ->assertJsonPath('message', 'Roles retrieved')
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'display_name']],
            ]);
    }

    /**
     * Verify roles without ROLES.FINDALL cannot list the role catalog.
     */
    public function test_roles_without_permission_cannot_list_roles(): void
    {
        foreach (['RECEPTIONIST', 'DOCTOR', 'PHARMACIST', 'CASHIER'] as $roleName) {
            Sanctum::actingAs($this->createUser($roleName));

            $this->getJson('/api/roles')
                ->assertForbidden()
                ->assertJsonPath('message', 'Missing permission: ROLES.FINDALL');
        }
    }

    /**
     * Verify unauthenticated requests are rejected.
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/roles')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    /**
     * Create a user assigned to the requested seeded role.
     */
    private function createUser(string $role): User
    {
        return User::factory()
            ->for(Role::query()->where('name', $role)->firstOrFail())
            ->create();
    }
}
