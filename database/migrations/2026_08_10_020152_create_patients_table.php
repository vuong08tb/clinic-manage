<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create patient profiles and their search indexes.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('full_name');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth');
            $table->string('phone', 20)->index();
            $table->string('email')->nullable()->unique();
            $table->string('address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('full_name');
        });
    }

    /**
     * Remove patient profiles.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
