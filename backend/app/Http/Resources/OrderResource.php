<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Refunds\RefundEligibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            /**
             * @var string
             *
             * @format date-time
             */
            'expiresAt' => $this->expires_at?->toIso8601ZuluString(),
            /**
             * @var string
             *
             * @format date-time
             */
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'paymentStatus' => $this->payment?->status,
            'event' => new OrderEventResource($this->event),
            'items' => OrderItemResource::collection($this->items),
            /**
             * The invoice appears once its PDF can be downloaded from `/orders/{id}/invoice`.
             */
            'invoice' => $this->invoice?->pdf_path === null ? null : new OrderInvoiceResource($this->invoice),
            /**
             * The last moment of a refund: 48 hours before the event starts. Null unless the order is `PAID`.
             *
             * @var string|null
             *
             * @format date-time
             */
            'refundableUntil' => $this->status === OrderStatus::Paid
                ? RefundEligibility::refundableUntil($this->event->starts_at, config('ticketflow.refund_cutoff_hours'))->toIso8601ZuluString()
                : null,
            'refund' => $this->refund === null ? null : new OrderRefundResource($this->refund),
            /**
             * The correction invoice of a refund appears once its PDF can be downloaded from
             * `/orders/{id}/correction-invoice`.
             */
            'correctionInvoice' => $this->correctionInvoice?->pdf_path === null ? null : new OrderInvoiceResource($this->correctionInvoice),
        ];
    }
}
