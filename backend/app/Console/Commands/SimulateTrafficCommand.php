<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Enums\Role;
use App\Exceptions\Domain\DomainException;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Orders\ReserveTickets;
use App\Services\Payments\StartPayment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('ticketflow:simulate-traffic {--orders=500 : How many orders to place}')]
#[Description('Place and pay orders for the events on sale through the real services, to show the queues in Horizon')]
class SimulateTrafficCommand extends Command
{
    public function handle(ReserveTickets $reserveTickets, StartPayment $startPayment): int
    {
        $orders = (int) $this->option('orders');

        if ($this->laravel->isProduction()) {
            $this->error('This command creates demo users and orders, so it does not run in production.');

            return self::FAILURE;
        }

        if ($orders < 1) {
            $this->error('--orders must be at least 1.');

            return self::FAILURE;
        }

        $ticketTypes = $this->ticketTypesOnSale();

        if ($ticketTypes->isEmpty()) {
            $this->error('No events on sale. Run php artisan db:seed first.');

            return self::FAILURE;
        }

        // Every buyer places at most two orders of up to three tickets, which stays within the limit of six.
        $buyers = $this->createBuyers(intdiv($orders + 1, 2));
        $results = [];

        for ($i = 0; $i < $orders; $i++) {
            $result = $this->placeOrder(
                $reserveTickets,
                $startPayment,
                $buyers[$i % $buyers->count()],
                $ticketTypes[$i % $ticketTypes->count()],
                1 + $i % 3,
            );

            $results[$result] = ($results[$result] ?? 0) + 1;
        }

        $this->table(['Result', 'Orders'], collect($results)->map(fn (int $count, string $result) => [$result, $count])->values()->all());
        $this->info('Payments, tickets, PDFs, invoices and e-mails now run on the queues: watch them in /horizon.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, TicketType>
     */
    private function ticketTypesOnSale(): Collection
    {
        return TicketType::query()
            ->whereHas('event', fn (Builder $events) => $events
                ->where('status', EventStatus::Published)
                ->where('starts_at', '>', now()))
            ->orderBy('id')
            ->get(['id', 'event_id']);
    }

    /**
     * Fresh accounts for every run, so the per-person limit of an earlier run does not refuse orders.
     * They share one hash, because hashing hundreds of passwords would take minutes.
     *
     * @return Collection<int, User>
     */
    private function createBuyers(int $count): Collection
    {
        $run = Str::lower(Str::random(6));
        $password = Hash::make(Str::random(32));

        $rows = array_map(fn (int $number) => [
            'name' => "Traffic Buyer {$run}-{$number}",
            'email' => "traffic-{$run}-{$number}@traffic.ticketflow.test",
            'password' => $password,
            'role' => Role::Attendee->value,
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, $count));

        User::query()->insert($rows);

        return User::query()->whereIn('email', array_column($rows, 'email'))->orderBy('id')->get(['id']);
    }

    private function placeOrder(ReserveTickets $reserveTickets, StartPayment $startPayment, User $buyer, TicketType $ticketType, int $quantity): string
    {
        try {
            $order = $reserveTickets->handle($buyer, $ticketType->event_id, [
                ['ticketTypeId' => $ticketType->id, 'quantity' => $quantity],
            ]);

            $startPayment->handle($order);
        } catch (DomainException $e) {
            return $e->errorCode;
        }

        return 'Payment started';
    }
}
