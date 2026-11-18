<?php

namespace App\Http\Resources;

use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
class OrderRefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * `PENDING` until the payment gateway confirms that the money went back.
             */
            'status' => $this->status,
            /**
             * The whole order total in grosz.
             */
            'amount' => $this->amount,
            'reason' => $this->reason,
            /**
             * @var string
             *
             * @format date-time
             */
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
