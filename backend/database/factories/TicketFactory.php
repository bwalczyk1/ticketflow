<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\OrderItem;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'position' => fake()->unique()->numberBetween(1, 1000000),
            'code' => fake()->uuid(),
            'status' => TicketStatus::Valid,
        ];
    }

    public function used(): static
    {
        return $this->state([
            'status' => TicketStatus::Used,
            'checked_in_at' => now(),
        ]);
    }

    public function void(): static
    {
        return $this->state(['status' => TicketStatus::Void]);
    }
}
