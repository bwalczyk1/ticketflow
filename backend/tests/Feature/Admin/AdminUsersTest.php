<?php

use App\Enums\Role;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin', 'email' => 'ada@example.com']);
});

describe('GET /api/admin/users', function () {
    it('lists users with pagination for an admin', function () {
        User::factory()->count(4)->create();
        $this->actingAs($this->admin);

        $this->getJson('/api/admin/users?limit=2&page=2&sort=-email')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta', ['page' => 2, 'limit' => 2, 'total' => 5, 'totalPages' => 3]);
    });

    it('returns only the public user fields', function () {
        $this->actingAs($this->admin);

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'id' => $this->admin->id,
                    'name' => 'Ada Admin',
                    'email' => 'ada@example.com',
                    'role' => 'ADMIN',
                ]],
                'meta' => ['page' => 1, 'limit' => 20, 'total' => 1, 'totalPages' => 1],
            ]);
    });

    it('filters by role', function () {
        $organizer = User::factory()->organizer()->create();
        User::factory()->create();
        $this->actingAs($this->admin);

        $this->getJson('/api/admin/users?role=ORGANIZER')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $organizer->id);
    });

    it('searches by name or email', function (string $search) {
        $anna = User::factory()->create(['name' => 'Anna Nowak', 'email' => 'contact@ticketflow.test']);
        User::factory()->create(['name' => 'Adam Lis', 'email' => 'adam@example.com']);
        $this->actingAs($this->admin);

        $this->getJson("/api/admin/users?q={$search}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $anna->id);
    })->with(['nowak', 'ticketflow.test']);

    it('combines the role filter with the search without leaking other roles', function () {
        User::factory()->organizer()->create(['name' => 'Anna Organizer', 'email' => 'first@example.com']);
        $attendee = User::factory()->create(['name' => 'Second', 'email' => 'anna@example.com']);
        $this->actingAs($this->admin);

        $this->getJson('/api/admin/users?role=ATTENDEE&q=anna')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $attendee->id);
    });

    it('keeps a stable order between pages when the sort column ties', function () {
        User::factory()->count(3)->create(['name' => 'Same Name']);
        $this->actingAs($this->admin);

        $ids = collect([1, 2, 3])->flatMap(
            fn (int $page) => $this->getJson("/api/admin/users?sort=name&limit=1&page={$page}&role=ATTENDEE")->json('data.*.id'),
        );

        expect($ids->all())->toBe(User::where('role', Role::Attendee)->orderBy('id')->pluck('id')->all());
    });

    it('rejects invalid list parameters with 400', function (string $query, string $field) {
        $this->actingAs($this->admin);

        $this->getJson("/api/admin/users?{$query}")
            ->assertStatus(400)
            ->assertJsonPath('error.details.0.field', $field);
    })->with([
        'unknown role' => ['role=OWNER', 'role'],
        'sort outside the whitelist' => ['sort=password', 'sort'],
        'limit over 100' => ['limit=101', 'limit'],
    ]);
});

it('changes the role of a user')->todo();
it('rejects an unknown role with 400')->todo();
it('answers 404 for an unknown user')->todo();
it('answers 401 without a session')->todo();
it('answers 403 for an organizer and an attendee')->todo();
it('lists events of all organizers for an admin')->todo();
