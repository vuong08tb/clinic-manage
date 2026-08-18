<?php

namespace Database\Factories;

use App\Models\Examination;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100000, 1000000);

        return [
            'examination_id' => Examination::factory(),
            'invoice_code' => fake()->unique()->numerify('INV-######'),
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'status' => Invoice::STATUS_UNPAID,
            'issued_at' => now(),
        ];
    }
}
