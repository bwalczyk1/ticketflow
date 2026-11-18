<?php

namespace App\Http\Resources;

use App\Data\PurchaseLimit;
use App\Models\Event;
use Illuminate\Http\Request;

/**
 * @mixin Event
 */
class EventDetailsResource extends EventResource
{
    public function __construct(Event $event, private ?PurchaseLimit $purchaseLimit)
    {
        parent::__construct($event);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            /**
             * Tickets per person for this event and how many of them the signed-in attendee
             * already holds or bought; `null` for guests and for other roles.
             */
            'purchaseLimit' => $this->purchaseLimit === null ? null : [
                'max' => $this->purchaseLimit->max,
                'used' => $this->purchaseLimit->used,
            ],
        ];
    }
}
