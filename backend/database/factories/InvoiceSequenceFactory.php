<?php

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\InvoiceSequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceSequence>
 */
class InvoiceSequenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => (int) now(config('app.business_timezone'))->format('Y'),
            'type' => InvoiceType::Invoice,
            'last_number' => 0,
        ];
    }
}
