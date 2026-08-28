<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add a partial index for the doctor schedule conflict check.
     *
     * The conflict query ignores cancelled appointments, so they are dead
     * weight in the index. The schema builder cannot express a WHERE clause
     * on an index, hence the raw SQL.
     */
    public function up(): void
    {
        DB::statement(
            "CREATE INDEX appointments_active_schedule_index
             ON appointments (doctor_id, scheduled_at)
             WHERE status <> 'cancelled'"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS appointments_active_schedule_index');
    }
};