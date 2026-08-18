<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Create or refresh the initial active administrator account.
     */
    public function run(): void
    {
        $adminRole = Role::query()
            ->where('name', 'ADMIN')
            ->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => 'admin@clinic.test'],
            [
                'role_id' => $adminRole->id,
                'name' => 'Clinic Admin',
                'password' => Hash::make('Admin@123'),
                // The initial administrator must be able to log in after seeding.
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
