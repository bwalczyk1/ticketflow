<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

function issueTokenFor(User $user): string
{
    Artisan::call('ticketflow:token', ['email' => $user->email]);

    return trim(Artisan::output());
}

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');
});

it('issues a 60 minute token for a user in the local environment', function () {
    $this->freezeTime();
    $user = User::factory()->create();

    $this->artisan('ticketflow:token', ['email' => $user->email])->assertSuccessful();

    expect($user->tokens()->sole()->expires_at->toIso8601String())
        ->toBe(now()->addMinutes(60)->toIso8601String());
});

it('refuses to run outside the local environment', function (string $environment) {
    app()->detectEnvironment(fn () => $environment);
    $user = User::factory()->create();

    $this->artisan('ticketflow:token', ['email' => $user->email])->assertFailed();

    expect($user->tokens()->count())->toBe(0);
})->with(['production', 'testing', 'staging']);

it('fails for an unknown email', function () {
    $this->artisan('ticketflow:token', ['email' => 'nobody@example.com'])->assertFailed();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('authenticates API requests with the issued token', function () {
    $user = User::factory()->create();

    $this->withToken(issueTokenFor($user))
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('stops accepting the token after 60 minutes', function () {
    $token = issueTokenFor(User::factory()->create());

    $this->travel(61)->minutes();

    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});

it('revokes the token on logout', function () {
    $token = issueTokenFor(User::factory()->create());

    $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});
