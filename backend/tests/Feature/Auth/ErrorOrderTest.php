<?php

use App\Exceptions\Domain\DomainException;
use App\Http\Requests\ApiFormRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;

class OrderProbeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['title' => ['required', 'string']];
    }
}

it('checks in the order 401, 403 role, 400, 404, 409 on the role change endpoint', function () {
    $admin = User::factory()->admin()->create();
    $attendee = User::factory()->create();

    $invalidBody = ['role' => 'OWNER'];
    $validBody = ['role' => 'ATTENDEE'];

    $this->patchJson('/api/admin/users/999999/role', $invalidBody)->assertUnauthorized();

    $this->actingAs($attendee);
    $this->patchJson('/api/admin/users/999999/role', $invalidBody)->assertForbidden();

    $this->actingAs($admin);
    $this->patchJson('/api/admin/users/999999/role', $invalidBody)->assertStatus(400);
    $this->patchJson('/api/admin/users/999999/role', $validBody)->assertNotFound();
    $this->patchJson("/api/admin/users/{$admin->id}/role", $validBody)->assertConflict();
});

it('checks in the order 401, 403 role, 400, 404, 403 ownership, 409', function () {
    Route::middleware(['api', 'auth:sanctum', 'role:ORGANIZER'])
        ->patch('api/probe/events/{id}', function (OrderProbeRequest $request, string $id) {
            $event = Event::findOrFail($id);

            if ($event->organizer_id !== $request->user()->id) {
                throw new AuthorizationException;
            }

            throw new DomainException('Event is cancelled', 'EVENT_CANCELLED');
        });

    $organizer = User::factory()->organizer()->create();
    $ownEvent = Event::factory()->for($organizer, 'organizer')->create();
    $foreignEvent = Event::factory()->create();

    $this->patchJson('/api/probe/events/999999', [])->assertUnauthorized();

    $this->actingAs(User::factory()->create());
    $this->patchJson('/api/probe/events/999999', [])->assertForbidden();

    $this->actingAs($organizer);
    $this->patchJson('/api/probe/events/999999', [])->assertStatus(400);
    $this->patchJson('/api/probe/events/999999', ['title' => 'New'])->assertNotFound();
    $this->patchJson("/api/probe/events/{$foreignEvent->id}", ['title' => 'New'])->assertForbidden();
    $this->patchJson("/api/probe/events/{$ownEvent->id}", ['title' => 'New'])->assertConflict();
});
