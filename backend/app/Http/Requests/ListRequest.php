<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

abstract class ListRequest extends ApiFormRequest
{
    public const MAX_LIMIT = 100;

    /**
     * Whitelist of API sort names mapped to database columns.
     *
     * @return array<string, string>
     */
    abstract protected function sortable(): array;

    abstract protected function defaultSort(): string;

    /**
     * @return array<string, mixed>
     */
    protected function filters(): array
    {
        return [];
    }

    public function rules(): array
    {
        $sorts = collect(array_keys($this->sortable()))
            ->flatMap(fn (string $name) => [$name, "-{$name}"])
            ->all();

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'sort' => ['sometimes', Rule::in($sorts)],
            ...$this->filters(),
        ];
    }

    public function page(): int
    {
        return (int) $this->validated('page', 1);
    }

    public function limit(): int
    {
        return (int) $this->validated('limit', 20);
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public function sortColumn(): array
    {
        $sort = $this->validated('sort', $this->defaultSort());
        $descending = str_starts_with($sort, '-');

        return [$this->sortable()[ltrim($sort, '-')], $descending ? 'desc' : 'asc'];
    }
}
