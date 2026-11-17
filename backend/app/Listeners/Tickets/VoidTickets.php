<?php

namespace App\Listeners\Tickets;

use App\Enums\QueueName;
use App\Events\Refunds\OrderRefunded;
use App\Services\Tickets\VoidOrderTickets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Attributes\Queue;

#[Queue(QueueName::Tickets)]
class VoidTickets implements ShouldQueue
{
    public function __construct(private VoidOrderTickets $voidOrderTickets) {}

    public function handle(OrderRefunded $event): void
    {
        $this->voidOrderTickets->handle($event->orderId);
    }
}
