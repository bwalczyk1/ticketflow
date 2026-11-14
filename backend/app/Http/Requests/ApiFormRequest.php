<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

abstract class ApiFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Unknown fields are an explicit error, instead of being silently dropped by validated().
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $known = collect(array_keys($this->rules()))
                ->map(fn (string $rule) => Str::before($rule, '.'))
                ->unique();

            foreach (array_keys($this->all()) as $field) {
                if (! $known->contains($field)) {
                    $validator->errors()->add($field, __('validation.unknown_field'));
                }
            }
        });
    }
}
