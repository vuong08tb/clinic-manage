<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePermission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsurePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, RbacSeeder::class]);
        config()->set('rbac.controllers.PermissionProbeController', 'USERS');

        Route::middleware(EnsurePermission::class)
            ->get('/api/_test/permissions', [PermissionProbeController::class, 'index']);

        Route::middleware(EnsurePermission::class)
            ->get('/api/_test/stats', [StatsController::class, 'show']);

        Route::middleware(EnsurePermission::class)
            ->post('/api/_test/invoices', [InvoiceController::class, 'store']);
    }

    public function test_user_with_permission_can_access_route(): void
    {
        $admin = $this->createUserWithRole('ADMIN', 'admin-permission@test.local');

        $this->actingAs($admin)
            ->get('/api/_test/permissions')
            ->assertOk();
    }

    public function test_user_without_permission_receives_forbidden_response(): void
    {
        $cashier = $this->createUserWithRole('CASHIER', 'cashier-permission@test.local');

        $this->actingAs($cashier)
            ->get('/api/_test/permissions')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Missing permission: USERS.FINDALL',
                'errors' => [],
            ]);
    }

    public function test_controller_action_override_resolves_stats_show_permission(): void
    {
        $admin = $this->createUserWithRole('ADMIN', 'admin-stats@test.local');

        $this->actingAs($admin)
            ->get('/api/_test/stats')
            ->assertOk();
    }

    public function test_receptionist_cannot_create_invoice(): void
    {
        $receptionist = $this->createUserWithRole('RECEPTIONIST', 'receptionist-permission@test.local');

        $this->actingAs($receptionist)
            ->post('/api/_test/invoices')
            ->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: INVOICES.CREATE');
    }

    public function test_unauthenticated_user_receives_unauthorized_response(): void
    {
        $this->get('/api/_test/permissions')->assertUnauthorized();
    }

    private function createUserWithRole(string $role, string $email): User
    {
        return User::query()->create([
            'role_id' => Role::query()->where('name', $role)->value('id'),
            'name' => $role,
            'email' => $email,
            'password' => 'password',
        ]);
    }
}

class PermissionProbeController
{
    public function index(): array
    {
        return ['success' => true];
    }
}

class StatsController
{
    public function show(): array
    {
        return ['success' => true];
    }
}

class InvoiceController
{
    public function store(): array
    {
        return ['success' => true];
    }
}
