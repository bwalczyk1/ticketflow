<?php

use App\Enums\WaitlistStatus;
use App\Models\SentNotification;
use App\Models\WaitlistEntry;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    $this->travelTo('2026-11-14 03:00:00');
});

function prune(): void
{
    test()->artisan('model:prune', ['--model' => [WaitlistEntry::class, SentNotification::class]])->assertSuccessful();
}

it('deletes closed waitlist entries untouched for more than 30 days', function (WaitlistStatus $status) {
    $old = WaitlistEntry::factory()->create(['status' => $status, 'updated_at' => '2026-10-15 02:59:59']);
    $recent = WaitlistEntry::factory()->create(['status' => $status, 'updated_at' => '2026-10-15 03:00:00']);

    prune();

    expect(WaitlistEntry::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(WaitlistEntry::query()->whereKey($recent->id)->exists())->toBeTrue();
})->with([WaitlistStatus::Expired, WaitlistStatus::Cancelled]);

it('keeps active and converted entries however old they are', function (WaitlistStatus $status) {
    $entry = WaitlistEntry::factory()->create(['status' => $status, 'updated_at' => '2025-01-01 00:00:00']);

    prune();

    expect(WaitlistEntry::query()->whereKey($entry->id)->exists())->toBeTrue();
})->with([WaitlistStatus::Waiting, WaitlistStatus::Offered, WaitlistStatus::Converted]);

it('deletes e-mail ledger rows older than 30 days', function () {
    $old = SentNotification::factory()->create(['sent_at' => '2026-10-15 02:59:59']);
    $recent = SentNotification::factory()->create(['sent_at' => '2026-10-15 03:00:00']);

    prune();

    expect(SentNotification::query()->pluck('id')->all())->toBe([$recent->id])
        ->and($old->id)->not->toBe($recent->id);
});

it('prunes once a day', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (ScheduledEvent $event) => str_contains((string) $event->command, 'model:prune'));

    expect($event?->expression)->toBe('0 0 * * *');
});
