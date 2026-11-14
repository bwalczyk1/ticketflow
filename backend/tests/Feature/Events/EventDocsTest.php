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
        ->and($this->spec['paths']['/events/{id}']['get']['security'])->toBe([]);
});

it('documents the 409 responses of the organizer endpoints', function (string $method, string $path, array $codes) {
    expect(documentedErrorCodes($this->spec, $method, $path, 409))->toEqualCanonicalizing($codes);
})->with([
    ['patch', '/organizer/events/{id}', ['EVENT_CLOSED']],
    ['delete', '/organizer/events/{id}', ['EVENT_HAS_SALES']],
    ['post', '/organizer/events/{id}/publish', ['INVALID_STATUS_TRANSITION', 'EVENT_NOT_PUBLISHABLE']],
    ['post', '/organizer/events/{id}/cancel', ['INVALID_STATUS_TRANSITION']],
    ['post', '/organizer/events/{id}/ticket-types', ['EVENT_CLOSED']],
    ['patch', '/organizer/events/{id}/ticket-types/{ticketTypeId}', ['EVENT_CLOSED', 'POOL_PROTECTED', 'RESERVATION_CONFLICT']],
    ['delete', '/organizer/events/{id}/ticket-types/{ticketTypeId}', ['EVENT_CLOSED', 'POOL_PROTECTED']],
]);

it('documents errors in the format the API really answers with', function (string $method, string $path, int $status, string $code) {
    expect(documentedErrorCodes($this->spec, $method, $path, $status))->toContain($code);
})->with([
    ['get', '/events', 400, 'VALIDATION_ERROR'],
    ['get', '/events/{id}', 404, 'NOT_FOUND'],
    ['post', '/organizer/events', 400, 'VALIDATION_ERROR'],
    ['post', '/organizer/events', 401, 'UNAUTHENTICATED'],
    ['post', '/organizer/events', 403, 'FORBIDDEN'],
    ['patch', '/organizer/events/{id}', 403, 'FORBIDDEN'],
    ['patch', '/organizer/events/{id}', 404, 'NOT_FOUND'],
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
    '/organizer/events/{id}/ticket-types',
]);

it('documents the item types of lists', function (string $path, string $schema) {
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath(
            "paths.{$path}.get.responses.200.content.application/json.schema.properties.data.items.\$ref",
            "#/components/schemas/{$schema}",
        );
})->with([
    ['/events', 'EventResource'],
    ['/organizer/events', 'EventManagementResource'],
    ['/organizer/events/{id}/ticket-types', 'TicketTypeManagementResource'],
]);

it('documents ids in the path as integers', function (string $path, string $method) {
    $parameters = collect($this->spec['paths'][$path][$method]['parameters'])->where('in', 'path');

    expect($parameters)->not->toBeEmpty();

    foreach ($parameters as $parameter) {
        expect($parameter['schema']['type'])->toBe('integer');
    }
})->with([
    ['/events/{id}', 'get'],
    ['/organizer/events/{id}', 'patch'],
    ['/organizer/events/{id}/ticket-types/{ticketTypeId}', 'patch'],
]);

it('documents the success statuses', function (string $method, string $path, int $status) {
    expect($this->spec['paths'][$path][$method]['responses'])->toHaveKey($status);
})->with([
    ['post', '/organizer/events', 201],
    ['post', '/organizer/events/{id}/ticket-types', 201],
    ['delete', '/organizer/events/{id}', 204],
]);

it('documents camelCase event fields', function () {
    expect($this->spec['components']['schemas']['EventResource']['properties'])
        ->toHaveKeys(['timezone', 'startsAt', 'endsAt', 'ticketTypes']);
});

it('documents the purchase limit of the event details as a nullable object', function () {
    $schema = $this->spec['paths']['/events/{id}']['get']['responses'][200]['content']['application/json']['schema'];
    $details = $this->spec['components']['schemas'][str_replace('#/components/schemas/', '', $schema['$ref'])];

    expect($details['properties']['purchaseLimit']['type'])->toBe(['object', 'null'])
        ->and($details['properties']['purchaseLimit']['properties'])->toBe(['max' => ['type' => 'integer'], 'used' => ['type' => 'integer']])
        ->and($details['required'])->toContain('purchaseLimit');
});

it('documents event dates as required date-time strings', function (string $resource) {
    $startsAt = $this->spec['components']['schemas'][$resource]['properties']['startsAt'];

    expect($startsAt)->toBe(['type' => 'string', 'format' => 'date-time']);
})->with(['EventResource', 'EventManagementResource']);
