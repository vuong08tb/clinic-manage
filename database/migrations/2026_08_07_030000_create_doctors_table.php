<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create doctor profiles linked to doctor users and specialties.
     */
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('specialty_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('license_number')->unique();
            $table->text('bio')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Remove doctor profiles.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
