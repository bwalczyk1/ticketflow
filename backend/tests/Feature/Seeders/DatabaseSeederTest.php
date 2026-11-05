<?php

use App\Enums\EventStatus;
use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

it('creates the demo accounts', function () {
    $this->seed();

    expect(User::query()->where('role', Role::Admin)->count())->toBe(1)
        ->and(User::query()->where('role', Role::Organizer)->count())->toBe(2)
        ->and(User::query()->where('role', Role::Attendee)->count())->toBeGreaterThanOrEqual(3);
});

it('documents a working password for the demo admin', function () {
    $this->seed();

    $admin = User::query()->where('email', 'admin@ticketflow.test')->firstOrFail();

    expect(Hash::check(DatabaseSeeder::DEMO_PASSWORD, $admin->password))->toBeTrue();
});

it('creates events in every status', function (EventStatus $status) {
    $this->seed();

    expect(Event::query()->where('status', $status)->exists())->toBeTrue();
})->with(EventStatus::cases());

it('gives every event ticket types and a matching organizer', function () {
    $this->seed();

    $events = Event::with(['ticketTypes', 'organizer'])->get();

    expect($events->every(fn (Event $event) => $event->ticketTypes->isNotEmpty()))->toBeTrue()
        ->and($events->every(fn (Event $event) => $event->organizer->role === Role::Organizer))->toBeTrue();
});

it('keeps upcoming events in the future and finished ones in the past', function () {
    $this->seed();

    expect(Event::query()->where('status', EventStatus::Published)->where('starts_at', '<=', now())->exists())->toBeFalse()
        ->and(Event::query()->where('status', EventStatus::Finished)->where('ends_at', '>=', now())->exists())->toBeFalse();
});

it('can run twice without duplicates', function () {
    $this->seed();

    $users = User::query()->count();
    $events = Event::query()->count();

    $this->seed();

    expect(User::query()->count())->toBe($users)
        ->and(Event::query()->count())->toBe($events);
});
