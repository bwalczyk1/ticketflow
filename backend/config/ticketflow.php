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
];
