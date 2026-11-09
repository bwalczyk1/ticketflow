<?php

namespace App\Services\Notifications;

use App\Models\SentNotification;
use Illuminate\Support\Facades\DB;

class NotificationLedger
{
    /**
     * Runs $send once per key. The claim is rolled back when sending fails, so a retry can send again.
     *
     * @param  callable(): void  $send
     */
    public function once(string $key, callable $send): bool
    {
        return DB::transaction(function () use ($key, $send) {
            $claimed = SentNotification::query()->insertOrIgnore([
                'key' => $key,
                'sent_at' => now(),
            ]);

            if ($claimed === 0) {
                return false;
            }

            $send();

            return true;
        });
    }
}
