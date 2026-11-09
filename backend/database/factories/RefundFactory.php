<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->paid(),
            'amount' => 10000,
            'reason' => 'Requested by the attendee',
            'status' => RefundStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => RefundStatus::Completed]);
    }

    public function failed(): static
    {
        return $this->state(['status' => RefundStatus::Failed]);
    }
}
