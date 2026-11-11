<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListTicketsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['createdAt' => 'created_at'];
    }

    protected function defaultSort(): string
    {
        return '-createdAt';
    }

    protected function filters(): array
    {
        return [
            'orderId' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
        ];
    }

    /**
     * @return array{orderId: ?int, status: ?string}
     */
    public function criteria(): array
    {
        $orderId = $this->validated('orderId');

        return [
            'orderId' => $orderId === null ? null : (int) $orderId,
            'status' => $this->validated('status'),
        ];
    }
}
