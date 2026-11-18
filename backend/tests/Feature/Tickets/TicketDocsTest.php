<?php

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');

    $this->spec = $this->getJson('/docs/api.json')->assertOk()->json();
});

/**
 * @return list<string>
 */
function ticketErrorCodes(array $spec, string $path, int $status): array
{
    $schema = $spec['paths'][$path]['get']['responses'][$status]['content']['application/json']['schema'] ?? [];
    $variants = $schema['anyOf'] ?? [$schema];

    return collect($variants)
        ->flatMap(fn (array $variant) => $variant['properties']['error']['properties']['code']['enum'] ?? [])
        ->all();
}

it('documents authentication of the ticket and sales endpoints', function (string $path) {
    expect($this->spec['paths'][$path]['get'])->not->toHaveKey('security')
        ->and(ticketErrorCodes($this->spec, $path, 401))->toContain('UNAUTHENTICATED');
})->with(['/tickets', '/tickets/{id}/pdf', '/organizer/events/{id}/sales']);

it('documents why a ticket PDF can be refused', function () {
    expect(ticketErrorCodes($this->spec, '/tickets/{id}/pdf', 409))->toEqualCanonicalizing(['TICKET_VOID', 'TICKET_PDF_NOT_READY'])
        ->and(ticketErrorCodes($this->spec, '/tickets/{id}/pdf', 404))->toContain('NOT_FOUND');
});

it('documents the sales summary schema', function () {
    expect($this->spec['paths']['/organizer/events/{id}/sales']['get']['responses'][200]['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/TicketSalesResource');
});
