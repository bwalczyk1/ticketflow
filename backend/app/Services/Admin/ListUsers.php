<?php

namespace App\Services\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListUsers
{
    /**
     * @param  array{0: string, 1: 'asc'|'desc'}  $sort
     * @return LengthAwarePaginator<int, User>
     */
    public function handle(?Role $role, ?string $search, array $sort, int $limit, int $page): LengthAwarePaginator
    {
        [$column, $direction] = $sort;

        return User::query()
            ->when($role !== null, fn (Builder $query) => $query->where('role', $role))
            ->when($search !== null, function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate(perPage: $limit, page: $page);
    }
}
