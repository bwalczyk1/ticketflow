<?php

return [
    'payment_gateway' => env('PAYMENT_GATEWAY', 'fake'),
    'fake_failure_rate' => env('FAKE_PAYMENT_FAILURE_RATE', 0.1),
    'stripe_secret' => env('STRIPE_SECRET'),
    'stripe_webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    'hold_minutes' => 15,
    'ticket_limit_per_person' => 6,
    'refund_cutoff_hours' => 48,
    'waitlist_offer_minutes' => 30,
    'reservation_attempts' => 5,

    // Closed waitlist entries and e-mail ledger rows are deleted after this many days. Failed jobs
    // stay in Horizon for 7 days, so a retried job never meets a ledger row that is already gone.
    'prune_after_days' => 30,
];
