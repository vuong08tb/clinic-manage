<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'method' => Payment::METHOD_PAYPAL,
            'status' => Payment::STATUS_PENDING,
            'provider' => 'paypal',
            'provider_order_id' => fake()->unique()->bothify('ORDER-##########'),
            'provider_capture_id' => null,
            'paid_at' => null,
            'note' => null,
        ];
    }

    /**
     * Indicate that the payment has been captured successfully.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => Payment::STATUS_COMPLETED,
            'provider_capture_id' => fake()->unique()->bothify('CAPTURE-##########'),
            'paid_at' => now(),
        ]);
    }
}
