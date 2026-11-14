<?php

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');

    $this->spec = $this->getJson('/docs/api.json')->assertOk()->json();
});

/**
 * @return list<string>
 */
function refundErrorCodes(array $spec, string $method, string $path, int $status): array
{
    $schema = $spec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'] ?? [];
    $variants = $schema['anyOf'] ?? [$schema];

    return collect($variants)
        ->flatMap(fn (array $variant) => $variant['properties']['error']['properties']['code']['enum'] ?? [])
        ->all();
}

it('documents the refund endpoint with its errors and the order schema', function () {
    $operation = $this->spec['paths']['/orders/{id}/refund']['post'];

    expect($operation['responses'][200]['content']['application/json']['schema']['$ref'])->toBe('#/components/schemas/OrderResource')
        ->and(refundErrorCodes($this->spec, 'post', '/orders/{id}/refund', 409))->toEqualCanonicalizing(['ORDER_NOT_REFUNDABLE', 'REFUND_WINDOW_CLOSED'])
        ->and(refundErrorCodes($this->spec, 'post', '/orders/{id}/refund', 404))->toContain('NOT_FOUND')
        ->and(refundErrorCodes($this->spec, 'post', '/orders/{id}/refund', 401))->toContain('UNAUTHENTICATED')
        ->and(refundErrorCodes($this->spec, 'post', '/orders/{id}/refund', 403))->not->toBeEmpty()
        ->and($operation['responses'])->toHaveKey(400);

    $body = $operation['requestBody']['content']['application/json']['schema'];
    $properties = $body['properties'] ?? $this->spec['components']['schemas'][str_replace('#/components/schemas/', '', $body['$ref'])]['properties'];

    expect($properties)->toHaveKey('reason')
        ->and($properties['reason']['maxLength'])->toBe(500);
});

it('documents the correction invoice download', function () {
    $operation = $this->spec['paths']['/orders/{id}/correction-invoice']['get'];

    expect($operation['responses'][200]['content'])->toHaveKey('application/pdf')
        ->and(refundErrorCodes($this->spec, 'get', '/orders/{id}/correction-invoice', 404))->toContain('NOT_FOUND', 'INVOICE_NOT_FOUND')
        ->and(refundErrorCodes($this->spec, 'get', '/orders/{id}/correction-invoice', 401))->toContain('UNAUTHENTICATED');
});

it('documents the refund fields of an order', function () {
    $properties = $this->spec['components']['schemas']['OrderResource']['properties'];

    expect($properties)->toHaveKeys(['refundableUntil', 'refund', 'correctionInvoice'])
        ->and($properties['refundableUntil']['format'])->toBe('date-time')
        ->and(collect($properties['refund']['anyOf'] ?? [])->pluck('$ref')->filter()->all())->toBe(['#/components/schemas/OrderRefundResource'])
        ->and(collect($properties['correctionInvoice']['anyOf'] ?? [])->pluck('$ref')->filter()->all())->toBe(['#/components/schemas/OrderInvoiceResource'])
        ->and($this->spec['components']['schemas']['OrderRefundResource']['properties'])->toHaveKeys(['status', 'amount', 'reason', 'createdAt'])
        ->and($this->spec['components']['schemas']['OrderRefundResource']['properties']['status']['$ref'] ?? null)->toBe('#/components/schemas/RefundStatus')
        ->and($this->spec['components']['schemas']['RefundStatus']['enum'])->toBe(['PENDING', 'SUCCEEDED', 'FAILED']);
});

it('documents refunds and check-ins in the sales summary', function () {
    expect($this->spec['components']['schemas']['TicketSalesResource']['properties'])->toHaveKeys(['refunds', 'checkedIn']);
});
