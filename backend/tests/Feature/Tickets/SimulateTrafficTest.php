<?php

use App\Enums\Role;
use App\Events\Orders\OrderPlaced;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function () {
    EventFacade::fake([OrderPlaced::class]);
});

it('places and starts paying orders spread across the events', function () {
    $concert = TicketType::factory()->create(['quantity' => 100]);
    $meetup = TicketType::factory()->create(['quantity' => 100]);

    $this->artisan('ticketflow:simulate-traffic', ['--orders' => 6])
        ->expectsTable(['Result', 'Orders'], [['Payment started', 6]])
        ->assertSuccessful();

    expect(Order::query()->count())->toBe(6)
        ->and(Payment::query()->count())->toBe(6)
        ->and(Order::query()->distinct()->pluck('event_id')->sort()->values()->all())
        ->toBe([$concert->event_id, $meetup->event_id]);

    EventFacade::assertDispatchedTimes(OrderPlaced::class, 6);
});

it('buys as simulated attendees', function () {
    TicketType::factory()->create();

    $this->artisan('ticketflow:simulate-traffic', ['--orders' => 4])->assertSuccessful();

    $buyers = User::query()->whereIn('id', Order::query()->select('user_id'))->get(['id', 'role']);

    expect($buyers)->not->toBeEmpty()
        ->and($buyers->every(fn (User $user) => $user->role === Role::Attendee))->toBeTrue();
});

it('reports orders refused by the business rules', function () {
    TicketType::factory()->create(['quantity' => 1]);

    $this->artisan('ticketflow:simulate-traffic', ['--orders' => 3])
        ->expectsTable(['Result', 'Orders'], [['Payment started', 1], ['TICKETS_SOLD_OUT', 2]])
        ->assertSuccessful();
});

it('uses only events that are on sale', function () {
    TicketType::factory()->for(Event::factory()->draft())->create();
    TicketType::factory()->for(Event::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]))->create();

    $this->artisan('ticketflow:simulate-traffic', ['--orders' => 2])
        ->expectsOutputToContain('No events on sale')
        ->assertFailed();

    expect(Order::query()->count())->toBe(0);
});

it('rejects a number of orders below one', function () {
    TicketType::factory()->create();

    $this->artisan('ticketflow:simulate-traffic', ['--orders' => 0])->assertFailed();
});

it('refuses to run in production', function () {
    TicketType::factory()->create();
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('ticketflow:simulate-traffic', ['--orders' => 1])->assertFailed();

    expect(Order::query()->count())->toBe(0);
});
