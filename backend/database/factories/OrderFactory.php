<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => Event::factory(),
            'status' => OrderStatus::Pending,
            'expires_at' => now()->addMinutes(config('ticketflow.hold_minutes')),
            'total' => 10000,
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => OrderStatus::Paid]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => OrderStatus::Expired,
            'expires_at' => now()->subMinute(),
            'seats_released_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => OrderStatus::Failed,
            'seats_released_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => OrderStatus::Cancelled,
            'seats_released_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(['status' => OrderStatus::Refunded]);
    }
}
