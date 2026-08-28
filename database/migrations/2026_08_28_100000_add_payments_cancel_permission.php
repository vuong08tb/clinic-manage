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
                'name' => 'PAYMENTS.CANCEL',
                'display_name' => 'Hủy thanh toán',
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['name'],
            ['display_name', 'updated_at'],
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'PAYMENTS.CANCEL')->delete();
    }
};
