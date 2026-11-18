<?php

namespace App\Listeners\Tickets;

use App\Enums\QueueName;
use App\Events\Refunds\OrderRefunded;
use App\Services\Tickets\VoidOrderTickets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class VoidTickets implements ShouldQueue
{
    public function __construct(private VoidOrderTickets $voidOrderTickets) {}

    public function viaQueue(): string
    {
        return QueueName::Tickets->value;
    }

    public function handle(OrderRefunded $event): void
    {
        $this->voidOrderTickets->handle($event->orderId);
    }

    public function failed(OrderRefunded $event, Throwable $exception): void
    {
        Log::error('Voiding tickets failed', ['order_id' => $event->orderId, 'error' => $exception::class]);
    }
}
