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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->enum('method', ['visa', 'paypal']);
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('provider')->default('paypal');
            $table->string('provider_order_id')->nullable();
            $table->string('provider_capture_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('provider_order_id');
        });
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_amount CHECK (amount > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
