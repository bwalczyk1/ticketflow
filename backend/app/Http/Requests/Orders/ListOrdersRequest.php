<?php

namespace App\Http\Requests\Orders;

use App\Http\Requests\ListRequest;

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
}
