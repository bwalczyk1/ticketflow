<?php

namespace App\Services\Events;

use App\Enums\EventStatus;
use App\Enums\OrderStatus;
use App\Jobs\Notifications\SendEventReminder;
use App\Models\Event;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SendEventReminders
{
    /**
     * @return int the number of events whose reminders were queued
     */
    public function handle(): int
    {
        $now = CarbonImmutable::now();
        [$from, $to] = ReminderWindow::bounds($now);

        return Event::query()
            ->where('status', EventStatus::Published)
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>=', $from)
            ->where('starts_at', '<', $to)
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn (int $eventId) => $this->remind($eventId, $now))
            ->count();
    }

    /**
     * Compare-and-set on reminder_sent_at: of two overlapping runs only the one that sets it
     * queues the e-mails. The jobs leave after the commit, so a rollback sends nothing.
     */
    private function remind(int $eventId, CarbonImmutable $now): bool
    {
        return DB::transaction(function () use ($eventId, $now) {
            $claimed = Event::query()
                ->whereKey($eventId)
                ->whereNull('reminder_sent_at')
                ->update(['reminder_sent_at' => $now]);

            if ($claimed === 0) {
                return false;
            }

            $attendeeIds = Order::query()
                ->where('event_id', $eventId)
                ->where('status', OrderStatus::Paid)
                ->distinct()
                ->orderBy('user_id')
                ->pluck('user_id');

            foreach ($attendeeIds as $userId) {
                SendEventReminder::dispatch($eventId, $userId);
            }

            return true;
        });
    }
}
