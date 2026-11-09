<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDays(30)->setTime(18, 0);

        return [
            'organizer_id' => User::factory()->organizer(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'venue' => fake()->company(),
            'city' => fake()->randomElement(['Krakow', 'Warsaw', 'Gdansk', 'Wroclaw']),
            'category' => fake()->randomElement(['concert', 'conference', 'meetup']),
            'timezone' => 'Europe/Warsaw',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(3),
            'status' => EventStatus::Published,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => EventStatus::Draft]);
    }

    public function published(): static
    {
        return $this->state(['status' => EventStatus::Published]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => EventStatus::Cancelled]);
    }

    public function finished(): static
    {
        $startsAt = now()->subDays(7)->setTime(18, 0);

        return $this->state([
            'status' => EventStatus::Finished,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(3),
        ]);
    }
}
