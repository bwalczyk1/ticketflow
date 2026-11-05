<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Arr;
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

it('logs in and returns the current user from GET /api/me', function () {
    $user = User::factory()->organizer()->create([
        'name' => 'Olivia Kowalska',
        'email' => 'olivia@example.com',
        'password' => 'correct-horse-battery',
    ]);

    $expected = [
        'id' => $user->id,
        'name' => 'Olivia Kowalska',
        'email' => 'olivia@example.com',
        'role' => 'ORGANIZER',
    ];

    $response = $this->postJson('/api/auth/login', [
        'email' => 'olivia@example.com',
        'password' => 'correct-horse-battery',
    ]);

    $response->assertOk()->assertExactJson($expected);

    continueSessionOf($response);

    $this->getJson('/api/me')->assertOk()->assertExactJson($expected);
});

it('finds the user regardless of the letter case of the login email', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => 'correct-horse-battery']);

    $this->postJson('/api/auth/login', [
        'email' => 'ANNA@Example.com',
        'password' => 'correct-horse-battery',
    ])->assertOk();
});

it('changes the session id on login', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => 'correct-horse-battery']);

    $guestSessionId = continueSessionOf($this->getJson('/sanctum/csrf-cookie'));

    $response = $this->postJson('/api/auth/login', [
        'email' => 'anna@example.com',
        'password' => 'correct-horse-battery',
    ])->assertOk();

    expect(continueSessionOf($response))->not->toBe($guestSessionId);

    $this->assertDatabaseMissing('sessions', ['id' => $guestSessionId]);
});

it('does not start a session for a client outside the SPA', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => 'correct-horse-battery']);

    $this->flushHeaders();

    $response = $this->postJson('/api/auth/login', [
        'email' => 'anna@example.com',
        'password' => 'correct-horse-battery',
    ]);

    $response->assertOk();

    expect($response->headers->getCookies())->toBeEmpty();
});

it('gives the same 401 INVALID_CREDENTIALS for a wrong email and a wrong password', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => 'correct-horse-battery']);

    $wrongEmail = $this->postJson('/api/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'correct-horse-battery',
    ]);

    $wrongPassword = $this->postJson('/api/auth/login', [
        'email' => 'anna@example.com',
        'password' => 'wrong-horse-battery',
    ]);

    $wrongEmail->assertUnauthorized()->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    $wrongPassword->assertUnauthorized();

    expect(Arr::except($wrongEmail->json('error'), 'requestId'))
        ->toBe(Arr::except($wrongPassword->json('error'), 'requestId'));
});

it('does not start a session after failed credentials', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => 'correct-horse-battery']);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'anna@example.com',
        'password' => 'wrong-horse-battery',
    ]);

    continueSessionOf($response);

    $this->getJson('/api/me')->assertUnauthorized();
});

it('compares against a dummy hash when the user does not exist', function () {
    Hash::shouldReceive('check')
        ->once()
        ->withArgs(function (string $password, string $hash) {
            $info = password_get_info($hash);

            return $password === 'correct-horse-battery'
                && $info['algoName'] === 'bcrypt'
                && $info['options']['cost'] === 12;
        })
        ->andReturnFalse();

    $this->postJson('/api/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'correct-horse-battery',
    ])->assertUnauthorized();
});

it('refuses a missing user even when the dummy hash matches', function () {
    Hash::shouldReceive('check')->once()->andReturnTrue();

    $this->postJson('/api/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'correct-horse-battery',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
});

it('reports missing login fields with 400', function () {
    $response = $this->postJson('/api/auth/login', [])->assertStatus(400);

    expect(array_column($response->json('error.details'), 'field'))
        ->toEqualCanonicalizing(['email', 'password']);
});

it('invalidates the session on logout', function () {
    User::factory()->create(['email' => 'anna@example.com', 'password' => 'correct-horse-battery']);

    $sessionId = continueSessionOf($this->postJson('/api/auth/login', [
        'email' => 'anna@example.com',
        'password' => 'correct-horse-battery',
    ]));

    $this->getJson('/api/me')->assertOk();
    forgetRequestState();

    $this->postJson('/api/auth/logout')->assertNoContent();
    forgetRequestState();

    $this->assertDatabaseMissing('sessions', ['id' => $sessionId]);
    $this->getJson('/api/me')->assertUnauthorized();
});

it('answers 401 from logout without a session', function () {
    $this->postJson('/api/auth/logout')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});

describe('with CSRF protection, which Laravel switches off in the testing environment', function () {
    beforeEach(function () {
        app()->detectEnvironment(fn () => 'local');

        User::factory()->create(['email' => 'anna@example.com', 'password' => 'correct-horse-battery']);
    });

    it('answers 419 CSRF_TOKEN_MISMATCH for an SPA request without a CSRF token', function (array $headers) {
        $this->withHeaders($headers)
            ->postJson('/api/auth/login', [
                'email' => 'anna@example.com',
                'password' => 'correct-horse-battery',
            ])
            ->assertStatus(419)
            ->assertJsonPath('error.code', 'CSRF_TOKEN_MISMATCH');
    })->with([
        'no fetch metadata' => [[]],
        'cross-site fetch' => [['Sec-Fetch-Site' => 'cross-site']],
    ]);

    it('accepts an SPA request with the token from /sanctum/csrf-cookie', function () {
        $csrfResponse = $this->getJson('/sanctum/csrf-cookie')->assertNoContent();
        $encryptedToken = $csrfResponse->getCookie('XSRF-TOKEN', decrypt: false)->getValue();

        continueSessionOf($csrfResponse);

        $this->withHeader('X-XSRF-TOKEN', $encryptedToken)
            ->postJson('/api/auth/login', [
                'email' => 'anna@example.com',
                'password' => 'correct-horse-battery',
            ])
            ->assertOk();
    });
});
