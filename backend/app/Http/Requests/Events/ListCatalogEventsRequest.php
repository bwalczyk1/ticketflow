<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\ListRequest;
use Illuminate\Support\Arr;

class ListCatalogEventsRequest extends ListRequest
{
    protected function sortable(): array
    {
        return ['startsAt' => 'starts_at', 'title' => 'title', 'createdAt' => 'created_at'];
    }

    protected function defaultSort(): string
    {
        return 'startsAt';
    }

    protected function filters(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date', new OffsetDate],
            'to' => ['nullable', 'date', new OffsetDate],
            'price_max' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{q?: ?string, city?: ?string, category?: ?string, from?: ?string, to?: ?string, price_max?: ?string}
     */
    public function criteria(): array
    {
        return Arr::only($this->validated(), ['q', 'city', 'category', 'from', 'to', 'price_max']);
    }
}
