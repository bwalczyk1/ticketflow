<?php

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Enums\WaitlistStatus;
use App\Models\Event as EventModel;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    $this->travelTo('2026-11-14 12:00:00');

    $this->attendee = User::factory()->create();
    $this->event = EventModel::factory()->create(['starts_at' => '2026-12-01 18:00:00', 'ends_at' => '2026-12-01 21:00:00']);

    // Two seats came free and are held for the offer.
    $this->pool = TicketType::factory()->for($this->event)->create(['quantity' => 10, 'sold' => 8, 'held' => 2, 'price' => 9000]);
    $this->offer = WaitlistEntry::factory()->for($this->attendee)->for($this->pool)->offered()->create(['quantity' => 2]);
});

function acceptOffer(WaitlistEntry|int $entry)
{
    $id = $entry instanceof WaitlistEntry ? $entry->id : $entry;

    return test()->postJson("/api/waitlist/{$id}/accept");
}

it('turns the offer into a pending order that holds the offered seats', function () {
    $this->actingAs($this->attendee);

    $response = acceptOffer($this->offer)->assertCreated();

    $order = Order::query()->sole();

    $response->assertJsonPath('id', $order->id)
        ->assertJsonPath('status', 'PENDING')
        ->assertJsonPath('total', 18000)
        ->assertJsonPath('expiresAt', '2026-11-14T12:15:00Z')
        ->assertJsonPath('event.id', $this->event->id)
        ->assertJsonPath('items.0.quantity', 2)
        ->assertJsonPath('items.0.unitPrice', 9000);

    expect($order->user_id)->toBe($this->attendee->id)
        ->and($this->offer->fresh())->status->toBe(WaitlistStatus::Converted)->order_id->toBe($order->id)
        ->and($this->pool->fresh())->held->toBe(2)->version->toBe(0);
});

it('shows the order of a converted entry in the list', function () {
    $this->actingAs($this->attendee);

    $orderId = acceptOffer($this->offer)->json('id');

    $this->getJson('/api/waitlist')
        ->assertJsonPath('data.0.status', 'CONVERTED')
        ->assertJsonPath('data.0.orderId', $orderId);
});

it('answers 410 when the offer has run out', function (Closure $expire) {
    $expire($this->offer);

    $this->actingAs($this->attendee);

    acceptOffer($this->offer)->assertStatus(410)->assertJsonPath('error.code', 'WAITLIST_OFFER_EXPIRED');

    expect(Order::query()->count())->toBe(0);
})->with([
    'at the exact second of offered_until' => [fn () => test()->travelTo('2026-11-14 12:30:00')],
    'already expired by the scheduler' => [fn (WaitlistEntry $offer) => $offer->update(['status' => WaitlistStatus::Expired])],
]);

it('answers 409 for an entry without an open offer', function (WaitlistStatus $status) {
    $this->offer->update(['status' => $status]);

    $this->actingAs($this->attendee);

    acceptOffer($this->offer)->assertConflict()->assertJsonPath('error.code', 'WAITLIST_OFFER_NOT_ACTIVE');
})->with([WaitlistStatus::Waiting, WaitlistStatus::Converted, WaitlistStatus::Cancelled]);

it('accepts an offer only once', function () {
    $this->actingAs($this->attendee);

    acceptOffer($this->offer)->assertCreated();
    acceptOffer($this->offer)->assertConflict()->assertJsonPath('error.code', 'WAITLIST_OFFER_NOT_ACTIVE');

    expect(Order::query()->count())->toBe(1);
});

it('keeps the limit of six tickets per event', function () {
    TicketFixtures::paidOrder([5], $this->attendee, $this->event);

    $this->actingAs($this->attendee);

    acceptOffer($this->offer)->assertConflict()->assertJsonPath('error.code', 'TICKET_LIMIT_EXCEEDED');

    expect($this->offer->fresh()->status)->toBe(WaitlistStatus::Offered);
});

it('refuses an offer of an event that is no longer on sale', function () {
    $this->event->forceFill(['status' => EventStatus::Cancelled])->save();

    $this->actingAs($this->attendee);

    acceptOffer($this->offer)->assertConflict()->assertJsonPath('error.code', 'SALES_CLOSED');
});

it('checks accepting in the order 401, 403 role, 404, 404 ownership, 409', function () {
    $foreign = WaitlistEntry::factory()->for($this->pool)->offered()->create();

    acceptOffer(999999)->assertUnauthorized();

    $this->actingAs(User::factory()->organizer()->create());
    acceptOffer(999999)->assertForbidden();

    $this->actingAs($this->attendee);
    acceptOffer(999999)->assertNotFound();
    acceptOffer($foreign)->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

    $this->offer->update(['status' => WaitlistStatus::Waiting]);

    acceptOffer($this->offer)->assertConflict();

    expect($foreign->fresh()->status)->toBe(WaitlistStatus::Offered)
        ->and(Order::query()->where('status', OrderStatus::Pending)->count())->toBe(0);
});
