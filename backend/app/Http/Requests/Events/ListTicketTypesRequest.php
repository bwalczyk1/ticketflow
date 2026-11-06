<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\ListRequest;

class ListTicketTypesRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['name' => 'name', 'price' => 'price'];
    }

    protected function defaultSort(): string
    {
        return 'price';
    }
}
