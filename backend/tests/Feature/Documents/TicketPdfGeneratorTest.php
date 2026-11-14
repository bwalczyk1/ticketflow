<?php

use App\Contracts\TicketPdfGenerator;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Documents\DompdfTicketPdfGenerator;
use App\Services\Documents\QrCodeImage;
use Illuminate\Support\Facades\Storage;

function ticketForEvent(array $event = [], array $user = []): Ticket
{
    $event = Event::factory()->create($event);

    $order = Order::factory()->paid()->create([
        'event_id' => $event->id,
        'user_id' => User::factory()->create($user)->id,
    ]);

    $ticketType = TicketType::factory()->create(['event_id' => $event->id, 'name' => 'Płyta główna']);
    $item = OrderItem::factory()->create(['order_id' => $order->id, 'ticket_type_id' => $ticketType->id]);

    return Ticket::factory()->create(['order_item_id' => $item->id]);
}

it('writes a PDF to tickets/{code}.pdf and returns the path', function () {
    Storage::fake('local');
    $ticket = ticketForEvent();

    $path = app(TicketPdfGenerator::class)->generate($ticket);

    expect($path)->toBe("tickets/{$ticket->code}.pdf");

    Storage::disk('local')->assertExists($path);

    expect(Storage::disk('local')->get($path))->toStartWith('%PDF-');
});

it('overwrites the same file when run twice', function () {
    Storage::fake('local');
    $ticket = ticketForEvent();
    $generator = app(TicketPdfGenerator::class);

    $first = $generator->generate($ticket);
    $second = $generator->generate($ticket);

    expect($second)->toBe($first)
        ->and(Storage::disk('local')->allFiles('tickets'))->toBe([$first]);
});

it('writes through a temporary file and rename', function () {
    $disk = Storage::fake('local');
    $ticket = ticketForEvent();
    $mock = Mockery::mock($disk);

    $mock->shouldReceive('put')->once()
        ->withArgs(fn (string $path) => $path !== "tickets/{$ticket->code}.pdf" && str_ends_with($path, '.tmp'))
        ->andReturnUsing(fn (string $path, string $contents) => $disk->put($path, $contents));

    $mock->shouldReceive('move')->once()
        ->withArgs(fn (string $from, string $to) => str_ends_with($from, '.tmp') && $to === "tickets/{$ticket->code}.pdf")
        ->andReturnUsing(fn (string $from, string $to) => $disk->move($from, $to));

    Storage::set('local', $mock);

    $path = app(TicketPdfGenerator::class)->generate($ticket);

    expect($disk->allFiles('tickets'))->toBe([$path]);
});

it('contains a QR code of the ticket code', function () {
    Storage::fake('local');
    $ticket = ticketForEvent();
    $generator = app(DompdfTicketPdfGenerator::class);

    $path = $generator->generate($ticket);

    expect($generator->html($ticket))->toContain(QrCodeImage::dataUri($ticket->code))
        ->and(Storage::disk('local')->get($path))->toContain('/Subtype /Image');
});

it('shows the start time from localStartsAt', function () {
    Storage::fake('local');

    $ticket = ticketForEvent([
        'starts_at' => '2026-12-01 18:00:00',
        'timezone' => 'Europe/Warsaw',
        'title' => 'Koncert Żółć',
        'venue' => 'Hala Łódź',
        'city' => 'Kraków',
    ], ['name' => 'Zażółć Gęślą']);

    $html = app(DompdfTicketPdfGenerator::class)->html($ticket);

    expect($html)->toContain('01.12.2026 19:00')
        ->not->toContain('18:00')
        ->toContain('Koncert Żółć', 'Hala Łódź', 'Kraków', 'Płyta główna', 'Zażółć Gęślą', $ticket->code);
});
