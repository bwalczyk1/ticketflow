<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => PaymentStatus::Pending,
            'amount' => 10000,
            'provider_ref' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state([
            'status' => PaymentStatus::Succeeded,
            'provider_ref' => 'fake_'.Str::lower(Str::random(16)),
        ]);
    }

    public function failed(): static
    {
        return $this->state(['status' => PaymentStatus::Failed]);
    }
}
