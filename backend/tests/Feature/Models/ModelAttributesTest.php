<?php

use App\Enums\EventStatus;
use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\EventSalesSummary;
use App\Models\Invoice;
use App\Models\InvoiceSequence;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SentNotification;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Models\WaitlistEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

dataset('models', [
    User::class,
    Event::class,
    TicketType::class,
    Order::class,
    OrderItem::class,
    Payment::class,
    Ticket::class,
    Invoice::class,
    InvoiceSequence::class,
    Refund::class,
    WaitlistEntry::class,
    EventSalesSummary::class,
    SentNotification::class,
]);

dataset('factory states', [
    'admin' => [fn () => User::factory()->admin()->create()->role, Role::Admin],
    'organizer' => [fn () => User::factory()->organizer()->create()->role, Role::Organizer],
    'attendee' => [fn () => User::factory()->create()->role, Role::Attendee],
    'published event' => [fn () => Event::factory()->create()->status, EventStatus::Published],
    'draft event' => [fn () => Event::factory()->draft()->create()->status, EventStatus::Draft],
    'cancelled event' => [fn () => Event::factory()->cancelled()->create()->status, EventStatus::Cancelled],
    'finished event' => [fn () => Event::factory()->finished()->create()->status, EventStatus::Finished],
    'pending order' => [fn () => Order::factory()->create()->status, OrderStatus::Pending],
    'paid order' => [fn () => Order::factory()->paid()->create()->status, OrderStatus::Paid],
    'expired order' => [fn () => Order::factory()->expired()->create()->status, OrderStatus::Expired],
    'failed order' => [fn () => Order::factory()->failed()->create()->status, OrderStatus::Failed],
    'cancelled order' => [fn () => Order::factory()->cancelled()->create()->status, OrderStatus::Cancelled],
    'refunded order' => [fn () => Order::factory()->refunded()->create()->status, OrderStatus::Refunded],
    'pending payment' => [fn () => Payment::factory()->create()->status, PaymentStatus::Pending],
    'succeeded payment' => [fn () => Payment::factory()->succeeded()->create()->status, PaymentStatus::Succeeded],
    'failed payment' => [fn () => Payment::factory()->failed()->create()->status, PaymentStatus::Failed],
    'valid ticket' => [fn () => Ticket::factory()->create()->status, TicketStatus::Valid],
    'used ticket' => [fn () => Ticket::factory()->used()->create()->status, TicketStatus::Used],
    'void ticket' => [fn () => Ticket::factory()->void()->create()->status, TicketStatus::Void],
    'pending refund' => [fn () => Refund::factory()->create()->status, RefundStatus::Pending],
    'succeeded refund' => [fn () => Refund::factory()->succeeded()->create()->status, RefundStatus::Succeeded],
    'failed refund' => [fn () => Refund::factory()->failed()->create()->status, RefundStatus::Failed],
    'waiting entry' => [fn () => WaitlistEntry::factory()->create()->status, WaitlistStatus::Waiting],
    'offered entry' => [fn () => WaitlistEntry::factory()->offered()->create()->status, WaitlistStatus::Offered],
    'converted entry' => [fn () => WaitlistEntry::factory()->converted()->create()->status, WaitlistStatus::Converted],
    'expired entry' => [fn () => WaitlistEntry::factory()->expired()->create()->status, WaitlistStatus::Expired],
    'cancelled entry' => [fn () => WaitlistEntry::factory()->cancelled()->create()->status, WaitlistStatus::Cancelled],
    'invoice' => [fn () => Invoice::factory()->create()->type, InvoiceType::Invoice],
]);

it('has a working factory', function (string $model) {
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue()
        ->and($model::query()->count())->toBeGreaterThanOrEqual(1);
})->with('models');

it('casts statuses to enums through factory states', function (Closure $status, mixed $expected) {
    expect($status())->toBe($expected);
})->with('factory states');

it('reads enums back from the database', function () {
    $order = Order::factory()->paid()->create();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

it('starts new records with the database defaults', function () {
    expect((new User)->role)->toBe(Role::Attendee)
        ->and((new Event)->status)->toBe(EventStatus::Draft)
        ->and((new Event)->timezone)->toBe('Europe/Warsaw');
});

it('hashes passwords and keeps the role out of mass assignment', function () {
    $user = new User(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'long-enough-password', 'role' => 'ADMIN']);

    expect($user->role)->toBe(Role::Attendee)
        ->and(Hash::check('long-enough-password', $user->password))->toBeTrue();
});

it('keeps owners out of mass assignment', function () {
    expect(new Event(['organizer_id' => 1]))->organizer_id->toBeNull()
        ->and(new Order(['user_id' => 1]))->user_id->toBeNull()
        ->and(new WaitlistEntry(['user_id' => 1]))->user_id->toBeNull();
});

it('keeps pool counters out of mass assignment', function () {
    $type = new TicketType(['sold' => 1, 'held' => 2, 'version' => 3]);

    expect($type->sold)->toBeNull()
        ->and($type->held)->toBeNull()
        ->and($type->version)->toBeNull();
});

it('casts dates to immutable UTC values', function () {
    $event = Event::factory()->create(['starts_at' => '2026-12-01 18:00:00', 'ends_at' => '2026-12-01 21:00:00']);
    $fresh = $event->fresh();

    expect($fresh->starts_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($fresh->starts_at->timezoneName)->toBe('UTC')
        ->and($fresh->starts_at->format('Y-m-d H:i:s'))->toBe('2026-12-01 18:00:00');
});

it('returns the start of an event in its own timezone', function (string $startsAt, string $timezone, string $expected) {
    $event = Event::factory()->make(['starts_at' => $startsAt, 'timezone' => $timezone]);

    expect($event->localStartsAt()->format('Y-m-d H:i P'))->toBe($expected)
        ->and($event->starts_at->timezoneName)->toBe('UTC');
})->with([
    'winter in Warsaw' => ['2026-12-01 18:00:00', 'Europe/Warsaw', '2026-12-01 19:00 +01:00'],
    'summer in Warsaw' => ['2026-07-01 17:00:00', 'Europe/Warsaw', '2026-07-01 19:00 +02:00'],
    'another continent' => ['2026-12-01 18:00:00', 'America/New_York', '2026-12-01 13:00 -05:00'],
    'next calendar day' => ['2026-12-31 23:30:00', 'Europe/Warsaw', '2027-01-01 00:30 +01:00'],
]);

it('addresses an invoice sequence by year and type', function () {
    $invoices = InvoiceSequence::factory()->create(['year' => 2026, 'type' => InvoiceType::Invoice, 'last_number' => 1]);
    $corrections = InvoiceSequence::factory()->create(['year' => 2026, 'type' => InvoiceType::Correction, 'last_number' => 7]);

    $invoices->last_number = 2;
    $invoices->save();

    expect($invoices->fresh()->last_number)->toBe(2)
        ->and($corrections->fresh()->last_number)->toBe(7);
});

it('uses the event as the key of the sales summary', function () {
    $summary = EventSalesSummary::factory()->create();

    expect($summary->getKey())->toBe($summary->event_id)
        ->and(EventSalesSummary::find($summary->event_id)?->is($summary))->toBeTrue();
});

it('keeps money as integer grosze', function () {
    $type = TicketType::factory()->create(['price' => 12999]);

    expect($type->fresh()->price)->toBe(12999);
});
