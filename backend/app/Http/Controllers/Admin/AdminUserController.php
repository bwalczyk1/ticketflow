<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Resources\UserResource;
use App\Services\Admin\ListUsers;
use App\Support\Pagination;

class AdminUserController extends Controller
{
    /**
     * @return array{data: mixed, meta: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function index(ListUsersRequest $request, ListUsers $listUsers): array
    {
        $users = $listUsers->handle(
            role: $request->enum('role', Role::class),
            search: $request->validated('q'),
            sort: $request->sortColumn(),
            limit: $request->limit(),
            page: $request->page(),
        );

        return Pagination::response($users, UserResource::class);
    }
}
