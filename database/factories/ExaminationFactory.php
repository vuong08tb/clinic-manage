<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Examination;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Examination>
 */
class ExaminationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory()->state([
                'scheduled_at' => fake()->dateTimeBetween('-1 month', '-1 minute'),
                'status' => Appointment::STATUS_COMPLETED,
            ]),
            'doctor_id' => fn (array $attributes): int => Appointment::query()
                ->findOrFail($attributes['appointment_id'])
                ->doctor_id,
            'patient_id' => fn (array $attributes): int => Appointment::query()
                ->findOrFail($attributes['appointment_id'])
                ->patient_id,
            'diagnosis' => fake()->sentence(),
            'notes' => fake()->optional()->paragraph(),
            'examined_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
