<?php

namespace App\Support;

class ConfigValidator
{
    private const GATEWAYS = ['fake', 'stripe'];

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public static function errors(array $config): array
    {
        $errors = [];

        if (empty($config['app_key'])) {
            $errors[] = 'APP_KEY must be set';
        }

        if (! in_array($config['payment_gateway'] ?? null, self::GATEWAYS, true)) {
            $errors[] = 'PAYMENT_GATEWAY must be one of: '.implode(', ', self::GATEWAYS);
        }

        $rate = $config['fake_failure_rate'] ?? null;

        if (! is_numeric($rate) || $rate < 0 || $rate > 1) {
            $errors[] = 'FAKE_PAYMENT_FAILURE_RATE must be a number between 0 and 1';
        }

        if (($config['payment_gateway'] ?? null) === 'stripe' && empty($config['stripe_secret'])) {
            $errors[] = 'STRIPE_SECRET must be set when PAYMENT_GATEWAY is stripe';
        }

        return $errors;
    }
}
