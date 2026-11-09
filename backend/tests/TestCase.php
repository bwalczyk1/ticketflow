<?php

namespace Tests;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Frames from these framework folders only pass the query on, so the caller is looked for above them.
     */
    private const QUERY_LAYERS = ['Database', 'Events', 'Support', 'Collections'];

    /**
     * @var list<string>
     */
    private array $selectStarQueries = [];

    protected function setUp(): void
    {
        parent::setUp();

        DB::listen(function (QueryExecuted $query) {
            $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            if (static::selectsAllColumns($query->sql) && static::issuedFromApp($frames)) {
                $this->selectStarQueries[] = $query->sql;
            }
        });
    }

    /**
     * Runs after a passing test, so a select * query from app/ fails it.
     */
    protected function assertPostConditions(): void
    {
        $this->assertSame([], $this->selectStarQueries, 'Code in app/ must select explicit columns.');
    }

    /**
     * True when a select list is * or table.*. A subquery inside exists() fetches no rows, so it is skipped.
     */
    public static function selectsAllColumns(string $sql): bool
    {
        $sql = preg_replace('/exists\s*\(\s*select\s+\*/i', 'exists(select 1', $sql);

        preg_match_all('/\bselect\s+(?:distinct\s+)?(.*?)\s+from\s/is', $sql, $matches);

        foreach ($matches[1] as $columns) {
            if (preg_match('/(^|,)\s*(`?\w+`?\.)?\*\s*(,|$)/', $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the first frame outside the framework's query layers is a file in app/.
     *
     * @param  list<array{file?: string}>  $frames
     */
    public static function issuedFromApp(array $frames): bool
    {
        $queryLayers = array_map(
            fn (string $folder) => base_path("vendor/laravel/framework/src/Illuminate/{$folder}/"),
            self::QUERY_LAYERS,
        );

        foreach ($frames as $frame) {
            $file = $frame['file'] ?? '';

            if ($file === '' || Str::startsWith($file, $queryLayers)) {
                continue;
            }

            return str_starts_with($file, base_path('app/'));
        }

        return false;
    }
}
