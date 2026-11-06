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
     * Takes a plain id, because route model binding would answer 404 before the role check and
     * the Form Request. The agreed order is 401, 403 role, 400, 404, 403 ownership, 409.
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    protected function ownedEvent(string $id): Event
    {
        $event = Event::query()->findOrFail($id);

        Gate::authorize('manage', $event);

        return $event;
    }
}
