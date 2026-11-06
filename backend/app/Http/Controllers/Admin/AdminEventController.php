<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListEventsRequest;
use App\Http\Resources\AdminEventResource;
use App\Services\Admin\ListEvents;
use App\Support\Pagination;

class AdminEventController extends Controller
{
    /**
     * @return array{data: mixed, meta: array{page: int, limit: int, total: int, totalPages: int}}
     */
    public function index(ListEventsRequest $request, ListEvents $listEvents): array
    {
        $events = $listEvents->handle($request->sortColumn(), $request->limit(), $request->page());

        return Pagination::response($events, AdminEventResource::class);
    }
}
