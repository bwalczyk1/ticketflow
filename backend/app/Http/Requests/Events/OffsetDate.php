<?php

namespace App\Http\Requests\Events;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A date without an offset is ambiguous, so 2026-12-01T19:00:00 is refused.
 */
class OffsetDate implements ValidationRule
{
    private const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            $fail('validation.offset_date')->translate();
        }
    }
}
