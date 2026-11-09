<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketType>
 */
class TicketTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Pool '.fake()->unique()->numberBetween(1, 1000000),
            'price' => fake()->numberBetween(20, 300) * 100,
            'quantity' => 100,
            'sold' => 0,
            'held' => 0,
            'version' => 0,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'sold' => $attributes['quantity'],
            'held' => 0,
        ]);
    }
}
