<?php

namespace Database\Factories;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Medicine>
 */
class MedicineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('MED-###??'),
            'name' => fake()->unique()->words(2, true),
            'unit' => fake()->randomElement(['Vỉ', 'Hộp', 'Chai', 'Ống', 'Viên']),
            'price' => fake()->randomFloat(2, 1000, 500000),
            'stock' => fake()->numberBetween(0, 500),
            'is_active' => true,
        ];
    }
}
