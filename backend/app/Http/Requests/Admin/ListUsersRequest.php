<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\ListRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['name' => 'name', 'email' => 'email', 'createdAt' => 'created_at'];
    }

    protected function defaultSort(): string
    {
        return '-createdAt';
    }

    protected function filters(): array
    {
        return [
            'role' => ['sometimes', Rule::enum(Role::class)],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
