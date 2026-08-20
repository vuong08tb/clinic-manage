<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('examination_id')->unique()->constrained('examinations')->restrictOnDelete();
            $table->string('invoice_code')->unique();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->enum('status', ['unpaid', 'paid', 'cancelled'])->default('unpaid')->index();
            $table->timestamp('issued_at');
            $table->timestamps();
        });
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_subtotal_check CHECK (subtotal >= 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_discount_check CHECK (discount >= 0)');
            DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_total_check CHECK (total >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
