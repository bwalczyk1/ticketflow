<?php

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');

    $this->spec = $this->getJson('/docs/api.json')->assertOk()->json();
});

/**
 * @return list<string>
 */
function waitlistErrorCodes(array $spec, string $method, string $path, int $status): array
{
    $schema = $spec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'] ?? [];

    return collect($schema['anyOf'] ?? [$schema])
        ->flatMap(fn (array $variant) => $variant['properties']['error']['properties']['code']['enum'] ?? [])
        ->all();
}

it('documents authentication of the waitlist endpoints', function (string $method, string $path) {
    expect(waitlistErrorCodes($this->spec, $method, $path, 401))->toContain('UNAUTHENTICATED')
        ->and(waitlistErrorCodes($this->spec, $method, $path, 403))->toContain('FORBIDDEN');
})->with([
    ['get', '/waitlist'],
    ['post', '/ticket-types/{id}/waitlist'],
    ['delete', '/ticket-types/{id}/waitlist'],
]);

it('documents why joining a waitlist can be refused', function () {
    expect(waitlistErrorCodes($this->spec, 'post', '/ticket-types/{id}/waitlist', 409))
        ->toEqualCanonicalizing(['SALES_CLOSED', 'ALREADY_ON_WAITLIST', 'WAITLIST_NOT_NEEDED', 'TICKET_LIMIT_EXCEEDED'])
        ->and(waitlistErrorCodes($this->spec, 'post', '/ticket-types/{id}/waitlist', 404))->toContain('NOT_FOUND')
        ->and(waitlistErrorCodes($this->spec, 'post', '/ticket-types/{id}/waitlist', 400))->toContain('VALIDATION_ERROR');
});

it('documents leaving a waitlist', function () {
    expect($this->spec['paths']['/ticket-types/{id}/waitlist']['delete']['responses'])->toHaveKey(204)
        ->and(waitlistErrorCodes($this->spec, 'delete', '/ticket-types/{id}/waitlist', 404))->toContain('NOT_FOUND');
});

it('documents accepting an offer with the order schema and its errors', function () {
    $path = '/waitlist/{id}/accept';

    expect($this->spec['paths'][$path]['post']['responses'][201]['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/OrderResource')
        ->and(waitlistErrorCodes($this->spec, 'post', $path, 410))->toBe(['WAITLIST_OFFER_EXPIRED'])
        ->and(waitlistErrorCodes($this->spec, 'post', $path, 409))
        ->toEqualCanonicalizing(['WAITLIST_OFFER_NOT_ACTIVE', 'SALES_CLOSED', 'TICKET_LIMIT_EXCEEDED'])
        ->and(waitlistErrorCodes($this->spec, 'post', $path, 404))->toContain('NOT_FOUND')
        ->and(waitlistErrorCodes($this->spec, 'post', $path, 401))->toContain('UNAUTHENTICATED');
});

it('documents the entry schema', function () {
    expect($this->spec['paths']['/ticket-types/{id}/waitlist']['post']['responses'][201]['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/WaitlistEntryResource')
        ->and($this->spec['paths']['/waitlist']['get']['responses'][200]['content']['application/json']['schema']['properties']['data']['items']['$ref'])
        ->toBe('#/components/schemas/WaitlistEntryResource')
        ->and($this->spec['components']['schemas']['WaitlistEntryResource']['properties'])
        ->toHaveKeys(['id', 'status', 'quantity', 'position', 'offeredUntil', 'orderId', 'ticketType', 'event', 'createdAt']);
});
