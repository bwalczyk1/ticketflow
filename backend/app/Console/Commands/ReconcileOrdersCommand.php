<?php

namespace App\Console\Commands;

use App\Services\Tickets\ReconcileOrders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ticketflow:reconcile-orders')]
#[Description('Send OrderPaid again for paid orders that still have no tickets after five minutes')]
class ReconcileOrdersCommand extends Command
{
    public function handle(ReconcileOrders $reconcileOrders): int
    {
        $this->info("Reconciled {$reconcileOrders->handle()} orders.");

        return self::SUCCESS;
    }
}
