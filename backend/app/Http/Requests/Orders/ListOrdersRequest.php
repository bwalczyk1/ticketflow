<?php

namespace App\Http\Requests\Orders;

use App\Enums\OrderStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['createdAt' => 'created_at', 'total' => 'total'];
    }

    protected function defaultSort(): string
    {
        return '-createdAt';
    }

    protected function filters(): array
    {
        return [
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ];
    }
}
