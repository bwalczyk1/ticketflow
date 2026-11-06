<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

abstract class OrganizerController extends Controller
{
    /**
     * Loaded here and not by route model binding, because binding would answer 404 before the
     * Form Request answers 400, and the agreed order is 400, 404, 403 ownership.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    protected function ownedEvent(int $id): Event
    {
        $event = Event::query()->findOrFail($id);

        Gate::authorize('manage', $event);

        return $event;
    }
}
