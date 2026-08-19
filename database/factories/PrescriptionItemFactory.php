<?php

namespace Database\Factories;

use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrescriptionItem>
 */
class PrescriptionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'prescription_id' => Prescription::factory(),
            'medicine_id' => Medicine::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'dosage' => fake()->randomElement(['1 viên/lần, ngày 2 lần', '2 viên/lần, ngày 3 lần']),
            'usage_instruction' => fake()->optional()->sentence(),
        ];
    }
}
