<?php

namespace Database\Factories;

use App\Enums\WaitlistStatus;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ticket_type_id' => TicketType::factory(),
            'quantity' => 1,
            'status' => WaitlistStatus::Waiting,
        ];
    }

    public function offered(): static
    {
        return $this->state([
            'status' => WaitlistStatus::Offered,
            'offered_until' => now()->addMinutes(config('ticketflow.waitlist_offer_minutes')),
        ]);
    }

    public function converted(): static
    {
        return $this->state(['status' => WaitlistStatus::Converted]);
    }

    public function expired(): static
    {
        return $this->state([
            'status' => WaitlistStatus::Expired,
            'offered_until' => now()->subMinute(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => WaitlistStatus::Cancelled]);
    }
}
