<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ListRequest;

class ListEventsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['title' => 'title', 'startsAt' => 'starts_at', 'createdAt' => 'created_at'];
    }

    protected function defaultSort(): string
    {
        return '-startsAt';
    }
}
