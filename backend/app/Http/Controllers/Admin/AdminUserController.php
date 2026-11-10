<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Services\Admin\ChangeUserRole;
use App\Services\Admin\ListUsers;
use App\Support\Pagination;

class AdminUserController extends Controller
{
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

    /**
     * Takes a plain id, because route model binding would answer 404 before validation answers 400.
     */
    public function updateRole(UpdateUserRoleRequest $request, string $id, ChangeUserRole $changeUserRole): UserResource
    {
        $user = $changeUserRole->handle($request->user(), $id, $request->enum('role', Role::class));

        return new UserResource($user);
    }
}
