<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Store every timestamp column as `timestamptz`.
     *
     * Values written before this migration were produced while the application
     * timezone was UTC, so they are re-interpreted as UTC and Postgres keeps
     * the same absolute instant. Reads then come back in the session timezone
     * (Asia/Ho_Chi_Minh) instead of a naive wall-clock value.
     */
    public function up(): void
    {
        $this->convert('timestamp without time zone', 'timestamptz', 'UTC');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->convert('timestamp with time zone', 'timestamp', 'UTC');
    }

    /**
     * Rewrite every column of the given type, preserving column defaults.
     */
    private function convert(string $fromType, string $toType, string $timezone): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columnsOfType($fromType) as $column) {
            $table = $column->table_name;
            $name = $column->column_name;
            $default = $column->column_default;

            if ($default !== null) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$name} DROP DEFAULT");
            }

            DB::statement(
                "ALTER TABLE {$table} ALTER COLUMN {$name} TYPE {$toType} "
                ."USING {$name} AT TIME ZONE '{$timezone}'"
            );

            if ($default !== null) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$name} SET DEFAULT {$default}");
            }
        }
    }

    /**
     * All base-table columns in the current schema matching the given type.
     *
     * @return array<int, object>
     */
    private function columnsOfType(string $type): array
    {
        return DB::select(
            <<<'SQL'
                SELECT c.table_name, c.column_name, c.column_default
                FROM information_schema.columns c
                JOIN information_schema.tables t
                    ON t.table_schema = c.table_schema
                    AND t.table_name = c.table_name
                WHERE c.table_schema = current_schema()
                    AND t.table_type = 'BASE TABLE'
                    AND c.data_type = ?
                ORDER BY c.table_name, c.column_name
            SQL,
            [$type]
        );
    }
};
