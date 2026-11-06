<?php

use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware('api')->prefix('api/probe')->group(function () {
        Route::get('duplicate', function () {
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

        Route::get('broken-sql', fn () => DB::select('select * from missing_table'));
    });
});

it('keeps the data of a duplicate registration out of the logs', function () {
    $logged = collect();

    Event::listen(MessageLogged::class, function (MessageLogged $log) use ($logged) {
        $exception = $log->context['exception'] ?? null;

        $logged->push($log->message.' '.$exception?->getMessage().' '.json_encode($log->context));
    });

    User::factory()->create(['email' => 'anna@example.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Anna Unique-Surname',
        'email' => 'anna@example.com',
        'password' => 'correct-horse-battery',
    ])->assertConflict();

    expect($logged->implode("\n"))
        ->not->toContain('anna@example.com')
        ->not->toContain('Unique-Surname')
        ->not->toContain('$2y$')
        ->not->toContain('correct-horse-battery');
});

it('does not report constraint errors answered with 409', function (string $path) {
    Exceptions::fake();

    $this->getJson("/api/probe/{$path}")->assertConflict();

    Exceptions::assertReportedCount(0);
})->with(['duplicate', 'referenced', 'check']);

it('still reports database errors answered with 500', function () {
    Exceptions::fake();

    $this->getJson('/api/probe/broken-sql')->assertInternalServerError();

    Exceptions::assertReportedCount(1);
});

it('still reports constraint errors outside the API, for example in jobs', function () {
    Exceptions::fake();

    $user = User::factory()->create();

    try {
        User::factory()->create(['email' => $user->email]);
    } catch (QueryException $e) {
        report($e);
    }

    Exceptions::assertReportedCount(1);
});
