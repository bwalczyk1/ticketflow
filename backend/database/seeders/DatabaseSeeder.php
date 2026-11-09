<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Shared by all demo accounts listed in the README. Never used outside local demos.
     */
    public const DEMO_PASSWORD = 'ticketflow-demo';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Keeps scripts/setup.sh repeatable on an existing database.
        if (User::query()->exists()) {
            return;
        }

        $this->user('Ada Admin', 'admin@ticketflow.test', User::factory()->admin());

        $olivia = $this->user('Olivia Organizer', 'olivia@ticketflow.test', User::factory()->organizer());
        $oscar = $this->user('Oscar Organizer', 'oscar@ticketflow.test', User::factory()->organizer());

        $this->user('Anna Attendee', 'anna@ticketflow.test', User::factory());
        $this->user('Adam Attendee', 'adam@ticketflow.test', User::factory());
        $this->user('Alicja Attendee', 'alicja@ticketflow.test', User::factory());

        $this->event($olivia, Event::factory()->published(), now()->addDays(14), [
            'title' => 'Krakow Jazz Night',
            'venue' => 'ICE Krakow',
            'city' => 'Krakow',
            'category' => 'concert',
        ], [
            'Standard' => [12000, 200],
            'VIP' => [35000, 20],
        ]);

        // A pool of five makes the sold-out and waitlist scenarios quick to show.
        $this->event($olivia, Event::factory()->published(), now()->addDays(45), [
            'title' => 'Laravel Krakow Meetup',
            'venue' => 'Cracow University of Technology',
            'city' => 'Krakow',
            'category' => 'meetup',
        ], [
            'Entry' => [2500, 5],
        ]);

        $this->event($olivia, Event::factory()->draft(), now()->addDays(60), [
            'title' => 'Winter Indie Showcase',
            'venue' => 'Klub Studio',
            'city' => 'Krakow',
            'category' => 'concert',
        ], [
            'Standard' => [8000, 150],
        ]);

        $this->event($oscar, Event::factory()->published(), now()->addDays(21), [
            'title' => 'Web Summit London',
            'venue' => 'ExCeL London',
            'city' => 'London',
            'category' => 'conference',
            'timezone' => 'Europe/London',
        ], [
            'Regular' => [49900, 300],
            'Student' => [19900, 50],
        ]);

        $this->event($oscar, Event::factory()->cancelled(), now()->addDays(30), [
            'title' => 'Gdansk Rock Festival',
            'venue' => 'Ergo Arena',
            'city' => 'Gdansk',
            'category' => 'concert',
        ], [
            'Standard' => [15000, 500],
        ]);

        $this->event($oscar, Event::factory()->finished(), now()->subDays(10), [
            'title' => 'Autumn Tech Conference',
            'venue' => 'EXPO XXI',
            'city' => 'Warsaw',
            'category' => 'conference',
        ], [
            'Regular' => [29900, 100],
        ]);
    }

    private function user(string $name, string $email, UserFactory $factory): User
    {
        return $factory->create([
            'name' => $name,
            'email' => $email,
            'password' => self::DEMO_PASSWORD,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array{0: int, 1: int}>  $ticketTypes  name => [price in grosze, quantity]
     */
    private function event(User $organizer, EventFactory $factory, CarbonInterface $day, array $attributes, array $ticketTypes): void
    {
        $timezone = $attributes['timezone'] ?? 'Europe/Warsaw';
        $startsAt = $day->copy()->setTimezone($timezone)->setTime(19, 0)->utc();

        $event = $factory->for($organizer, 'organizer')->create([
            ...$attributes,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(3),
        ]);

        foreach ($ticketTypes as $name => [$price, $quantity]) {
            TicketType::factory()->for($event)->create([
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity,
            ]);
        }
    }
}
