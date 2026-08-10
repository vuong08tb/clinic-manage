<?php

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('BN-######'),
            'full_name' => fake()->name(),
            'gender' => fake()->randomElement(Patient::GENDERS),
            'date_of_birth' => fake()->dateTimeBetween('-90 years', '-1 day')->format('Y-m-d'),
            'phone' => fake()->numerify('09########'),
            'email' => fake()->boolean(70) ? fake()->unique()->safeEmail() : null,
            'address' => fake()->optional()->address(),
        ];
    }
}
