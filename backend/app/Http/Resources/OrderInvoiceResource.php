<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Services\Documents\InvoiceNumbering;
use App\Support\ApiDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class OrderInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Year and number, for example `2026/1`.
             */
            'number' => InvoiceNumbering::label($this->year, $this->number),
            /**
             * @var string
             *
             * @format date-time
             */
            'issuedAt' => ApiDate::format($this->created_at),
        ];
    }
}
