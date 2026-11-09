<?php

namespace Database\Factories;

use App\Models\SentNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SentNotification>
 */
class SentNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'order:'.fake()->unique()->numberBetween(1, 1000000).':tickets',
            'sent_at' => now(),
        ];
    }
}
