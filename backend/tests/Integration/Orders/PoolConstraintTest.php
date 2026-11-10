<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Models\TicketType;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

it('stops a direct update above the pool and answers 409 without database details', function (array $change) {
    $pool = TicketType::factory()->create(['quantity' => 5, 'sold' => 3, 'held' => 2]);
    $failure = null;

    try {
        DB::table('ticket_types')->where('id', $pool->id)->update($change);
    } catch (QueryException $e) {
        $failure = $e;
    }

    expect($failure)->not->toBeNull()
        ->and($failure->errorInfo[1])->toBe(3819)
        ->and($pool->fresh())->sold->toBe(3)->held->toBe(2);

    $response = (new ApiExceptionRenderer)($failure, Request::create('/api/orders', 'POST'));

    expect($response->getStatusCode())->toBe(409)
        ->and($response->getData(true)['error']['code'])->toBe('CHECK_VIOLATION')
        ->and($response->getContent())->not->toContain('ticket_types');
})->with([
    'held above the pool' => [['held' => 3]],
    'sold above the pool' => [['sold' => 4]],
    'negative held' => [['held' => -1]],
]);
