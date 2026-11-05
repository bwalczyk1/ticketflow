<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

/**
 * Drops what the test process keeps in memory between requests, so the next request
 * can only authenticate with the cookie it sends, like a real browser.
 */
function forgetRequestState(): void
{
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
}

function continueSessionOf(TestResponse $response): string
{
    $sessionId = $response->getCookie(config('session.cookie'))->getValue();

    test()->withCredentials()->withCookie(config('session.cookie'), $sessionId);
    forgetRequestState();

    return $sessionId;
}

beforeEach(function () {
    config([
        'sanctum.stateful' => ['localhost'],
        'session.driver' => 'database',
    ]);

    forgetRequestState();
    $this->withHeader('Referer', 'http://localhost/');
});

it('registers an attendee and starts a session', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Anna Nowak',
        'email' => 'anna@example.com',
        'password' => 'correct-horse-battery',
    ]);

    $user = User::sole();

    $response->assertCreated()->assertExactJson([
        'id' => $user->id,
        'name' => 'Anna Nowak',
        'email' => 'anna@example.com',
        'role' => 'ATTENDEE',
    ]);

    expect($user->role)->toBe(Role::Attendee)
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue();

    continueSessionOf($response);

    $this->getJson('/api/me')->assertOk()->assertJsonPath('id', $user->id);
});

it('normalizes the email to lower case on registration', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Anna Nowak',
        'email' => 'Anna.NOWAK@Example.com',
        'password' => 'correct-horse-battery',
    ])->assertCreated()->assertJsonPath('email', 'anna.nowak@example.com');

    $this->assertDatabaseHas('users', ['email' => 'anna.nowak@example.com']);
});

it('rejects a role in the registration body with 400', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Mallory',
        'email' => 'mallory@example.com',
        'password' => 'correct-horse-battery',
        'role' => 'ADMIN',
    ])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonPath('error.details.0.field', 'role');

    $this->assertDatabaseCount('users', 0);
});

it('rejects a password shorter than 10 or longer than 72 characters', function (int $length) {
    $this->postJson('/api/auth/register', [
        'name' => 'Anna Nowak',
        'email' => 'anna@example.com',
        'password' => str_repeat('a', $length),
    ])
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', 'password');

    $this->assertDatabaseCount('users', 0);
})->with([9, 73]);

it('accepts a password of exactly 10 and 72 characters', function (int $length) {
    $this->postJson('/api/auth/register', [
        'name' => 'Anna Nowak',
        'email' => 'anna@example.com',
        'password' => str_repeat('a', $length),
    ])->assertCreated();
})->with([10, 72]);

it('reports every missing registration field with 400', function () {
    $response = $this->postJson('/api/auth/register', [])->assertStatus(400);

    expect(array_column($response->json('error.details'), 'field'))
        ->toEqualCanonicalizing(['name', 'email', 'password']);
});

it('rejects a malformed email on registration with 400', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Anna Nowak',
        'email' => 'not-an-email',
        'password' => 'correct-horse-battery',
    ])
        ->assertStatus(400)
        ->assertJsonPath('error.details.0.field', 'email');
});

it('answers 409 EMAIL_TAKEN for a duplicate email that differs only in letter case', function () {
    User::factory()->create(['email' => 'anna@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Anna Nowak',
        'email' => 'ANNA@example.com',
        'password' => 'correct-horse-battery',
    ])
        ->assertConflict()
        ->assertJsonPath('error.code', 'EMAIL_TAKEN');

    $this->assertDatabaseCount('users', 1);
});

it('answers 401 from GET /api/me without a session', function () {
    $this->getJson('/api/me')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

it('logs in and returns the current user from GET /api/me')->todo();
it('changes the session id on login')->todo();
it('gives the same 401 INVALID_CREDENTIALS for a wrong email and a wrong password')->todo();
it('compares against a dummy hash when the user does not exist')->todo();
it('invalidates the session on logout')->todo();
it('answers 419 CSRF_TOKEN_MISMATCH for an SPA request without a CSRF token')->todo();
