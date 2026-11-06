<?php

namespace App\Http\Requests\Events;

use App\Http\Requests\ApiFormRequest;
use Carbon\CarbonImmutable;

abstract class EventRequest extends ApiFormRequest
{
    /**
     * Validated fields under their column names, with dates converted to UTC.
     *
     * @return array<string, mixed>
     */
    public function eventData(): array
    {
        return collect($this->validated())
            ->mapWithKeys(fn (string $value, string $field) => match ($field) {
                'startsAt' => ['starts_at' => CarbonImmutable::parse($value)->utc()],
                'endsAt' => ['ends_at' => CarbonImmutable::parse($value)->utc()],
                default => [$field => $value],
            })
            ->all();
    }
}
