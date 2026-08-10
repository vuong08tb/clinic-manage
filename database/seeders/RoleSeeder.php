<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('roles')->upsert([
            ['name' => 'ADMIN', 'display_name' => 'Quản trị viên', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'RECEPTIONIST', 'display_name' => 'Lễ tân', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'DOCTOR', 'display_name' => 'Bác sĩ', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'PHARMACIST', 'display_name' => 'Dược sĩ', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'CASHIER', 'display_name' => 'Thu ngân', 'created_at' => $now, 'updated_at' => $now],
        ], ['name'], ['display_name', 'updated_at']);
    }
}
