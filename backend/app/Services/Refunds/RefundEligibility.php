<?php

namespace App\Services\Refunds;

use App\Enums\OrderStatus;
use App\Exceptions\Domain\OrderNotRefundable;
use App\Exceptions\Domain\RefundWindowClosed;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class RefundEligibility
{
    /**
     * Counted in real hours on the UTC timeline, so a change of clocks in the event's time zone
     * neither shortens nor stretches the window.
     */
    public static function refundableUntil(CarbonInterface $startsAt, int $cutoffHours): CarbonImmutable
    {
        return $startsAt->toImmutable()->utc()->subHours($cutoffHours);
    }

    /**
     * The deadline itself still allows a refund.
     *
     * @throws OrderNotRefundable
     * @throws RefundWindowClosed
     */
    public static function ensureRefundable(OrderStatus $status, CarbonInterface $startsAt, CarbonInterface $now, int $cutoffHours): void
    {
        if (! $status->canTransitionTo(OrderStatus::Refunded)) {
            throw new OrderNotRefundable;
        }

        if ($now->greaterThan(self::refundableUntil($startsAt, $cutoffHours))) {
            throw new RefundWindowClosed;
        }
    }
}
