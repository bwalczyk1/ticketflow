<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventSalesSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSalesSummary>
 */
class EventSalesSummaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'tickets_sold' => 0,
            'revenue' => 0,
            'refunds' => 0,
            'checked_in' => 0,
        ];
    }
}
