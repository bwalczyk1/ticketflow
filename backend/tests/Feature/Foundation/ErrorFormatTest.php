<?php

use App\Exceptions\Domain\DomainException;
use App\Http\Requests\ApiFormRequest;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;

class StrictProbeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['name' => ['required', 'string']];
    }
}

beforeEach(function () {
    Route::middleware('api')->prefix('api/probe')->group(function () {
        Route::get('auth', fn () => 'ok')->middleware('auth:sanctum');
        Route::get('role', fn () => 'ok')->middleware(['auth:sanctum', 'role:ADMIN']);
        Route::post('strict', fn (StrictProbeRequest $request) => $request->validated());

        Route::get('domain', function () {
            throw new DomainException('Sold out', 'TICKETS_SOLD_OUT', 409);
        });

        Route::get('crash', function () {
            throw new RuntimeException('secret detail');
        });

        Route::get('model', fn () => User::findOrFail(999999));

        Route::get('duplicate', function () {
            $user = User::factory()->create();
            DB::table('users')->insert(['name' => 'x', 'email' => $user->email, 'password' => 'x', 'role' => 'ATTENDEE']);
        });

        Route::get('duplicate-other', function () {
            $type = TicketType::factory()->create();
            DB::table('ticket_types')->insert(['event_id' => $type->event_id, 'name' => $type->name, 'price' => 1, 'quantity' => 1]);
        });

        Route::get('referenced', function () {
            OrderItem::factory()->create()->ticketType->delete();
        });

        Route::get('check', function () {
            $type = TicketType::factory()->create(['quantity' => 5]);
            DB::table('ticket_types')->where('id', $type->id)->update(['sold' => 6]);
        });

        Route::get('csrf', function () {
            throw new TokenMismatchException;
        });
    });
});

function assertErrorEnvelope($response, int $status, string $code): void
{
    $response->assertStatus($status)
        ->assertJsonPath('error.code', $code)
        ->assertJsonStructure(['error' => ['code', 'message', 'requestId']])
        ->assertHeader('X-Request-Id', $response->json('error.requestId'));
}

it('returns 401 without authentication', function () {
    assertErrorEnvelope($this->getJson('/api/probe/auth'), 401, 'UNAUTHENTICATED');
});

it('returns 401 to a guest that does not ask for JSON', function (string $path) {
    assertErrorEnvelope($this->get($path, ['Accept' => 'text/html']), 401, 'UNAUTHENTICATED');
})->with(['/api/probe/auth', '/api/me', '/api/tickets/1/pdf']);

it('returns 403 for a wrong role', function () {
    $this->actingAs(User::factory()->create());

    assertErrorEnvelope($this->getJson('/api/probe/role'), 403, 'FORBIDDEN');
});

it('returns 404 for an unknown endpoint and a missing model', function () {
    assertErrorEnvelope($this->getJson('/api/nope'), 404, 'NOT_FOUND');
    assertErrorEnvelope($this->getJson('/api/probe/model'), 404, 'NOT_FOUND');
});

it('returns 400 with field details on validation errors', function () {
    $response = $this->postJson('/api/probe/strict', []);

    assertErrorEnvelope($response, 400, 'VALIDATION_ERROR');
    $response->assertJsonPath('error.details.0.field', 'name');
});

it('rejects unknown body fields', function () {
    $response = $this->postJson('/api/probe/strict', ['name' => 'a', 'role' => 'ADMIN']);

    assertErrorEnvelope($response, 400, 'VALIDATION_ERROR');
    $response->assertJsonPath('error.details.0.field', 'role');
});

it('answers validation details in English by default', function () {
    $missing = $this->postJson('/api/probe/strict', []);

    $missing->assertStatus(400)
        ->assertJsonPath('error.details.0.message', 'The name field is required.')
        ->assertHeader('Content-Language', 'en')
        ->assertHeader('Vary', 'Accept-Language');

    $this->postJson('/api/probe/strict', ['name' => 'a', 'role' => 'ADMIN'])
        ->assertJsonPath('error.details.0.message', 'This field is not allowed.');
});

it('answers validation details in Polish when the client asks for it', function (string $header) {
    $missing = $this->postJson('/api/probe/strict', [], ['Accept-Language' => $header]);

    $missing->assertStatus(400)
        ->assertJsonPath('error.message', 'Invalid request data')
        ->assertJsonPath('error.details.0.message', 'Pole imię i nazwisko jest wymagane.')
        ->assertHeader('Content-Language', 'pl');

    $unknown = $this->postJson('/api/probe/strict', ['name' => 'a', 'role' => 'ADMIN'], ['Accept-Language' => $header]);

    $unknown->assertStatus(400)
        ->assertJsonPath('error.details.0.field', 'role')
        ->assertJsonPath('error.details.0.message', 'To pole jest niedozwolone.');
})->with(['pl', 'pl-PL,pl;q=0.9']);

it('falls back to English for an unsupported language', function () {
    $response = $this->postJson('/api/probe/strict', [], ['Accept-Language' => 'de']);

    $response->assertJsonPath('error.details.0.message', 'The name field is required.')
        ->assertHeader('Content-Language', 'en');
});

it('answers 400 INVALID_JSON for a malformed JSON body', function (string $body) {
    $response = $this->call('POST', '/api/probe/strict', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);

    assertErrorEnvelope($response, 400, 'INVALID_JSON');

    $response->assertJsonPath('error.message', 'Malformed JSON body.')
        ->assertJsonMissingPath('error.details');
})->with([
    'unclosed object' => ['{bad'],
    'trailing comma' => ['{"name": "a",}'],
]);

it('answers 400 INVALID_JSON on the login endpoint', function () {
    $response = $this->call('POST', '/api/auth/login', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], '{"email": ');

    assertErrorEnvelope($response, 400, 'INVALID_JSON');
});

it('accepts a valid JSON body and a request without a body', function () {
    $this->call('POST', '/api/probe/strict', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"name": "a"}')
        ->assertOk();

    $this->call('POST', '/api/probe/strict', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

it('renders domain exceptions with their code and status', function () {
    assertErrorEnvelope($this->getJson('/api/probe/domain'), 409, 'TICKETS_SOLD_OUT');
});

it('hides details of unexpected errors', function () {
    config(['app.debug' => true]);
    $response = $this->getJson('/api/probe/crash');

    assertErrorEnvelope($response, 500, 'INTERNAL_ERROR');
    expect($response->getContent())->not->toContain('secret detail')->not->toContain('trace');
});

it('maps database constraint errors without leaking schema details', function () {
    $response = $this->getJson('/api/probe/duplicate');

    assertErrorEnvelope($response, 409, 'EMAIL_TAKEN');
    expect($response->getContent())->not->toContain('users_email_unique');
});

it('maps other constraint errors to 409 without schema details', function (string $path, string $code) {
    $response = $this->getJson("/api/probe/{$path}");

    assertErrorEnvelope($response, 409, $code);
    expect($response->getContent())->not->toContain('ticket_types')->not->toContain('SQLSTATE');
})->with([
    ['duplicate-other', 'UNIQUE_VIOLATION'],
    ['referenced', 'FOREIGN_KEY_VIOLATION'],
    ['check', 'CHECK_VIOLATION'],
]);

it('returns 419 for a CSRF mismatch', function () {
    assertErrorEnvelope($this->getJson('/api/probe/csrf'), 419, 'CSRF_TOKEN_MISMATCH');
});

it('returns 405 for a wrong method', function () {
    assertErrorEnvelope($this->postJson('/api/probe/domain'), 405, 'METHOD_NOT_ALLOWED');
});

it('sets a request id on successful responses too', function () {
    $this->actingAs(User::factory()->create());

    expect($this->getJson('/api/probe/auth')->headers->get('X-Request-Id'))->toBeUuid();
});

it('does not report expected domain errors', function () {
    Exceptions::fake();

    $this->getJson('/api/probe/domain');

    Exceptions::assertNotReported(DomainException::class);
});

it('rejects overselling at the database level', function () {
    $type = TicketType::factory()->create(['quantity' => 5, 'sold' => 0, 'held' => 0]);

    expect(fn () => DB::table('ticket_types')->where('id', $type->id)->update(['sold' => 6]))
        ->toThrow(QueryException::class);
});
