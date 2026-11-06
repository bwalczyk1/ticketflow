<?php

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');

    $this->spec = $this->getJson('/docs/api.json')->assertOk()->json();
});

/**
 * Error codes documented for one status of an operation, read from the response schema.
 *
 * @return list<string>
 */
function documentedErrorCodes(array $spec, string $method, string $path, int $status): array
{
    $schema = $spec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'] ?? [];
    $variants = $schema['anyOf'] ?? [$schema];

    return collect($variants)
        ->flatMap(fn (array $variant) => $variant['properties']['error']['properties']['code']['enum'] ?? [])
        ->all();
}

it('describes bearer security for the organizer endpoints only', function () {
    expect($this->spec['components']['securitySchemes']['http']['scheme'])->toBe('bearer')
        ->and($this->spec['security'])->toBe([['http' => []]])
        ->and($this->spec['paths']['/organizer/events']['post'])->not->toHaveKey('security')
        ->and($this->spec['paths']['/events']['get']['security'])->toBe([])
        ->and($this->spec['paths']['/events/{event}']['get']['security'])->toBe([]);
});

it('documents the 409 responses of the organizer endpoints', function (string $method, string $path, array $codes) {
    expect(documentedErrorCodes($this->spec, $method, $path, 409))->toEqualCanonicalizing($codes);
})->with([
    ['patch', '/organizer/events/{event}', ['EVENT_CLOSED']],
    ['delete', '/organizer/events/{event}', ['EVENT_HAS_SALES']],
    ['post', '/organizer/events/{event}/publish', ['INVALID_STATUS_TRANSITION', 'EVENT_NOT_PUBLISHABLE']],
    ['post', '/organizer/events/{event}/cancel', ['INVALID_STATUS_TRANSITION']],
    ['post', '/organizer/events/{event}/ticket-types', ['EVENT_CLOSED']],
    ['patch', '/organizer/events/{event}/ticket-types/{ticketType}', ['EVENT_CLOSED', 'POOL_PROTECTED', 'RESERVATION_CONFLICT']],
    ['delete', '/organizer/events/{event}/ticket-types/{ticketType}', ['EVENT_CLOSED', 'POOL_PROTECTED']],
]);

it('documents errors in the format the API really answers with', function (string $method, string $path, int $status, string $code) {
    expect(documentedErrorCodes($this->spec, $method, $path, $status))->toContain($code);
})->with([
    ['get', '/events', 400, 'VALIDATION_ERROR'],
    ['get', '/events/{event}', 404, 'NOT_FOUND'],
    ['post', '/organizer/events', 400, 'VALIDATION_ERROR'],
    ['post', '/organizer/events', 401, 'UNAUTHENTICATED'],
    ['post', '/organizer/events', 403, 'FORBIDDEN'],
    ['patch', '/organizer/events/{event}', 403, 'FORBIDDEN'],
    ['patch', '/organizer/events/{event}', 404, 'NOT_FOUND'],
]);

it('does not document the default 422 of Laravel', function () {
    expect($this->spec['paths']['/organizer/events']['post']['responses'])->not->toHaveKey(422);
});

it('documents the shape of lists', function (string $path) {
    $schema = $this->spec['paths'][$path]['get']['responses'][200]['content']['application/json']['schema'];

    expect($schema['properties'])->toHaveKeys(['data', 'meta'])
        ->and($schema['properties']['data']['type'])->toBe('array')
        ->and($schema['properties']['meta']['properties'])->toHaveKeys(['page', 'limit', 'total', 'totalPages']);
})->with([
    '/events',
    '/organizer/events',
    '/organizer/events/{event}/ticket-types',
]);

it('documents the success statuses', function (string $method, string $path, int $status) {
    expect($this->spec['paths'][$path][$method]['responses'])->toHaveKey($status);
})->with([
    ['post', '/organizer/events', 201],
    ['post', '/organizer/events/{event}/ticket-types', 201],
    ['delete', '/organizer/events/{event}', 204],
]);

it('documents camelCase event fields', function () {
    expect($this->spec['components']['schemas']['EventResource']['properties'])
        ->toHaveKeys(['timezone', 'startsAt', 'endsAt', 'ticketTypes']);
});

it('documents event dates as required date-time strings', function (string $resource) {
    $startsAt = $this->spec['components']['schemas'][$resource]['properties']['startsAt'];

    expect($startsAt)->toBe(['type' => 'string', 'format' => 'date-time']);
})->with(['EventResource', 'EventManagementResource']);
