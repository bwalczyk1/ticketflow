<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }
}
