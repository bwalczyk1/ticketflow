<?php

use App\Enums\InvoiceType;
use App\Services\Documents\DocumentPaths;

it('builds the ticket path tickets/{code}.pdf', function () {
    expect(DocumentPaths::ticket('0b9a7c1e-4f2d-4a53-8f7e-1c2d3e4f5a6b'))
        ->toBe('tickets/0b9a7c1e-4f2d-4a53-8f7e-1c2d3e4f5a6b.pdf');
});

it('builds the invoice path invoices/{year}/{type}-{number}.pdf', function () {
    expect(DocumentPaths::invoice(2026, InvoiceType::Invoice, 12))->toBe('invoices/2026/invoice-12.pdf')
        ->and(DocumentPaths::invoice(2027, InvoiceType::Correction, 3))->toBe('invoices/2027/correction-3.pdf');
});
