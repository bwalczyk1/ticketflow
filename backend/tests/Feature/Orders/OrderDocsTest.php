<?php

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');

    $this->spec = $this->getJson('/docs/api.json')->assertOk()->json();
});

/**
 * @return list<string>
 */
function orderErrorCodes(array $spec, string $method, string $path, int $status): array
{
    $schema = $spec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'] ?? [];
    $variants = $schema['anyOf'] ?? [$schema];

    return collect($variants)
        ->flatMap(fn (array $variant) => $variant['properties']['error']['properties']['code']['enum'] ?? [])
        ->all();
}

it('documents authentication of the purchase endpoints', function (string $method, string $path) {
    expect($this->spec['paths'][$path][$method])->not->toHaveKey('security')
        ->and(orderErrorCodes($this->spec, $method, $path, 401))->toContain('UNAUTHENTICATED');
})->with([
    ['get', '/orders'],
    ['post', '/orders'],
    ['get', '/orders/{id}'],
    ['post', '/orders/{id}/pay'],
    ['post', '/orders/{id}/cancel'],
]);

it('documents the conflicts of the purchase endpoints', function (string $method, string $path, array $codes) {
    expect(orderErrorCodes($this->spec, $method, $path, 409))->toEqualCanonicalizing($codes);
})->with([
    ['post', '/orders', ['SALES_CLOSED', 'TICKET_LIMIT_EXCEEDED', 'TICKETS_SOLD_OUT', 'RESERVATION_CONFLICT']],
    ['post', '/orders/{id}/pay', ['PAYMENT_ALREADY_STARTED', 'ORDER_NOT_PAYABLE', 'SALES_CLOSED']],
    ['post', '/orders/{id}/cancel', ['PAYMENT_ALREADY_STARTED', 'INVALID_STATUS_TRANSITION']],
]);

it('documents the expired reservation of the pay endpoint', function () {
    expect(orderErrorCodes($this->spec, 'post', '/orders/{id}/pay', 410))->toBe(['RESERVATION_EXPIRED']);
});

it('documents missing orders', function (string $method, string $path) {
    expect(orderErrorCodes($this->spec, $method, $path, 404))->toContain('NOT_FOUND');
})->with([
    ['post', '/orders'],
    ['get', '/orders/{id}'],
    ['post', '/orders/{id}/pay'],
    ['post', '/orders/{id}/cancel'],
]);

it('documents the success statuses with the order schema', function (string $method, string $path, int $status) {
    expect($this->spec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema']['$ref'])
        ->toBe('#/components/schemas/OrderResource');
})->with([
    ['post', '/orders', 201],
    ['get', '/orders/{id}', 200],
    ['post', '/orders/{id}/pay', 202],
    ['post', '/orders/{id}/cancel', 200],
]);

it('documents the item type of the order list', function () {
    expect($this->spec['paths']['/orders']['get']['responses'][200]['content']['application/json']['schema']['properties']['data']['items']['$ref'])
        ->toBe('#/components/schemas/OrderResource');
});

it('documents camelCase order fields and the request body', function () {
    expect($this->spec['components']['schemas']['OrderResource']['properties'])
        ->toHaveKeys(['id', 'status', 'total', 'expiresAt', 'createdAt', 'paymentStatus', 'event', 'items'])
        ->and($this->spec['paths']['/orders']['post']['requestBody']['content']['application/json']['schema']['properties'] ?? $this->spec['components']['schemas']['StoreOrderRequest']['properties'])
        ->toHaveKeys(['eventId', 'items']);
});

it('documents order ids in the path as integers', function () {
    $parameters = collect($this->spec['paths']['/orders/{id}/pay']['post']['parameters'])->where('in', 'path');

    expect($parameters->first()['schema']['type'])->toBe('integer');
});
