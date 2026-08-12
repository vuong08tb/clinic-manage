<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Role;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define a valid doctor profile for tests and seed data.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fn (): int => User::factory()
                ->for(Role::query()->where('name', 'DOCTOR')->firstOrFail())
                ->create()
                ->getKey(),
            'specialty_id' => Specialty::factory(),
            'license_number' => fake()->unique()->bothify('VN-DOC-####-????'),
            'bio' => fake()->optional()->paragraph(),
        ];
    }
}
