<?php

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->paid(),
            'type' => InvoiceType::Invoice,
            'year' => (int) now(config('app.business_timezone'))->format('Y'),
            'number' => fake()->unique()->numberBetween(1, 1000000),
        ];
    }

    public function correction(Invoice $original, Refund $refund): static
    {
        return $this->state([
            'order_id' => $original->order_id,
            'refund_id' => $refund->id,
            'corrects_id' => $original->id,
            'type' => InvoiceType::Correction,
        ]);
    }
}
