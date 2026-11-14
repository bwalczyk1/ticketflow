<?php

namespace App\Http\Requests\Waitlist;

use App\Enums\WaitlistStatus;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListWaitlistRequest extends ListRequest
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
            'status' => ['nullable', Rule::enum(WaitlistStatus::class)],
        ];
    }
}
