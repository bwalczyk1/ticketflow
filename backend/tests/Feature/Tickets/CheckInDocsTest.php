<?php

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');

    $this->spec = $this->getJson('/docs/api.json')->assertOk()->json();
    $this->operation = $this->spec['paths']['/organizer/events/{id}/check-in']['post'];
});

/**
 * @return list<string>
 */
function checkInErrorCodes(array $operation, int $status): array
{
    $schema = $operation['responses'][$status]['content']['application/json']['schema'] ?? [];

    return collect($schema['anyOf'] ?? [$schema])
        ->flatMap(fn (array $variant) => $variant['properties']['error']['properties']['code']['enum'] ?? [])
        ->all();
}

it('documents the errors of the check-in', function (int $status, array $codes) {
    expect(checkInErrorCodes($this->operation, $status))->toContain(...$codes);
})->with([
    [401, ['UNAUTHENTICATED']],
    [403, ['FORBIDDEN']],
    [404, ['NOT_FOUND', 'TICKET_NOT_FOUND']],
    [409, ['CHECK_IN_CLOSED', 'TICKET_ALREADY_USED', 'TICKET_VOID']],
]);

it('documents the checked-in ticket and the request body', function () {
    $ticket = $this->operation['responses'][200]['content']['application/json']['schema']['properties']['ticket'];

    expect($ticket['$ref'] ?? null)->toBe('#/components/schemas/CheckedInTicketResource')
        ->and($this->operation['requestBody']['content']['application/json']['schema']['properties'] ?? $this->spec['components']['schemas']['CheckInRequest']['properties'])->toHaveKey('code');
});
