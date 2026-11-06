<?php

namespace App\Http\Requests\Events;

use App\Enums\EventStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListOrganizerEventsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['startsAt' => 'starts_at', 'title' => 'title', 'createdAt' => 'created_at'];
    }

    protected function defaultSort(): string
    {
        return '-createdAt';
    }

    protected function filters(): array
    {
        return [
            'status' => ['nullable', Rule::enum(EventStatus::class)],
        ];
    }
}
