<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class Pagination
{
    /**
     * Builds the list format shared by all endpoints: { data, meta }.
     * PaginationReturnTypeExtension documents this shape in the API docs.
     *
     * @param  class-string<JsonResource>  $resource
     * @return array{data: AnonymousResourceCollection, meta: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public static function response(LengthAwarePaginator $paginator, string $resource): array
    {
        return [
            'data' => $resource::collection($paginator->getCollection()),
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ];
    }
}
