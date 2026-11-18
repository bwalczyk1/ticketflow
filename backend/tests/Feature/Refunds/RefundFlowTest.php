<?php

use App\Enums\EventStatus;
use App\Enums\InvoiceType;
use App\Enums\RefundStatus;
use App\Enums\TicketStatus;
use App\Events\Orders\OrderPaid;
use App\Events\Orders\SeatsReleased;
use App\Listeners\Documents\IssueInvoice;
use App\Mail\EventCancelledMail;
use App\Mail\RefundMail;
use App\Models\EventSalesSummary;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Facades\Event as EventBus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tickets\TicketFixtures;

beforeEach(function () {
    Storage::fake('local');
    Mail::fake();
    EventBus::fake([SeatsReleased::class]);

    $this->attendee = User::factory()->create();
    $this->order = TicketFixtures::paidOrder([2], $this->attendee);
    $this->tickets = TicketFixtures::issueTickets($this->order);
    $this->pool = TicketType::query()->where('event_id', $this->order->event_id)->sole();

    app(IssueInvoice::class)->handle(new OrderPaid($this->order->id));
});

it('turns one refund into money back, void tickets, a correction, free seats, statistics and an e-mail', function () {
    $this->actingAs($this->attendee)->postJson("/api/orders/{$this->order->id}/refund")->assertOk();

    $correction = Invoice::query()->where('order_id', $this->order->id)->where('type', InvoiceType::Correction)->sole();

    expect(Refund::query()->sole()->status)->toBe(RefundStatus::Succeeded)
        ->and(TicketFixtures::ticketsOf($this->order)->pluck('status')->unique()->all())->toBe([TicketStatus::Void])
        ->and($this->pool->fresh()->sold)->toBe(0)
        ->and(EventSalesSummary::query()->find($this->order->event_id)?->only(['tickets_sold', 'revenue', 'refunds']))
        ->toBe(['tickets_sold' => 0, 'revenue' => 0, 'refunds' => $this->order->total]);

    Storage::disk('local')->assertExists($correction->pdf_path);
    Mail::assertSent(RefundMail::class, fn (RefundMail $mail) => $mail->hasTo($this->attendee->email));
    EventBus::assertDispatched(SeatsReleased::class);

    $this->getJson("/api/orders/{$this->order->id}")
        ->assertJsonPath('refund.status', 'SUCCEEDED')
        ->assertJsonPath('correctionInvoice.number', "{$correction->number}/{$correction->year}");

    $this->get("/api/orders/{$this->order->id}/correction-invoice")->assertOk();
});

it('refunds everybody when the organizer cancels the event', function () {
    $organizer = $this->order->event->organizer;

    $this->actingAs($organizer)->postJson("/api/organizer/events/{$this->order->event_id}/cancel")->assertOk();

    expect($this->order->event->fresh()->status)->toBe(EventStatus::Cancelled)
        ->and(Refund::query()->sole()->status)->toBe(RefundStatus::Succeeded)
        ->and($this->pool->fresh()->sold)->toBe(0);

    Mail::assertSent(EventCancelledMail::class, fn (EventCancelledMail $mail) => $mail->hasTo($this->attendee->email));
    Mail::assertSent(RefundMail::class);
    EventBus::assertNotDispatched(SeatsReleased::class);
});
