<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->upsert(
            [[
                'name' => 'MEDICINES.LOWSTOCK',
                'display_name' => 'Xem danh sách thuốc sắp hết',
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['display_name', 'updated_at'],
        );
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'MEDICINES.LOWSTOCK')
            ->delete();
    }
};