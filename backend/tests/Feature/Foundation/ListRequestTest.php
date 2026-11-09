<?php

use App\Http\Requests\ListRequest;
use App\Models\User;
use App\Support\Pagination;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

class UserListProbeRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['createdAt' => 'created_at', 'name' => 'name'];
    }

    protected function defaultSort(): string
    {
        return '-createdAt';
    }

    protected function filters(): array
    {
        return ['q' => ['sometimes', 'string']];
    }
}

beforeEach(function () {
    Route::middleware('api')->get('api/probe/users', function (UserListProbeRequest $request) {
        [$column, $direction] = $request->sortColumn();

        $paginator = User::query()
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate(perPage: $request->limit(), page: $request->page());

        return [...Pagination::response($paginator, JsonResource::class), 'sorted' => [$column, $direction]];
    });
});

it('lists with default paging and sorting', function () {
    User::factory()->count(3)->create();

    $this->getJson('/api/probe/users')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta', ['page' => 1, 'limit' => 20, 'total' => 3, 'totalPages' => 1])
        ->assertJsonPath('sorted', ['created_at', 'desc']);
});

it('reads the page and limit', function () {
    User::factory()->count(5)->create();

    $this->getJson('/api/probe/users?page=2&limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta', ['page' => 2, 'limit' => 2, 'total' => 5, 'totalPages' => 3]);
});

it('maps API sort names to whitelisted columns', function (string $sort, array $expected) {
    $this->getJson("/api/probe/users?sort={$sort}")
        ->assertOk()
        ->assertJsonPath('sorted', $expected);
})->with([
    ['name', ['name', 'asc']],
    ['-name', ['name', 'desc']],
    ['createdAt', ['created_at', 'asc']],
]);

it('accepts the maximum limit', function () {
    $this->getJson('/api/probe/users?limit=100')->assertOk();
});

it('rejects invalid list parameters', function (string $query, string $field) {
    $this->getJson("/api/probe/users?{$query}")
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', $field);
})->with([
    'limit over 100' => ['limit=101', 'limit'],
    'limit below 1' => ['limit=0', 'limit'],
    'page below 1' => ['page=0', 'page'],
    'sort outside the whitelist' => ['sort=password', 'sort'],
    'unknown filter' => ['role=ADMIN', 'role'],
]);

it('accepts declared filters', function () {
    $this->getJson('/api/probe/users?q=anna')->assertOk();
});
