<?php

use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Integration tests commit for real, so a second connection can see the data.
pest()->extend(TestCase::class)
    ->use(DatabaseMigrations::class)
    ->in('Integration');

/**
 * A separate connection to the test database, for deterministic interleaving of two clients.
 */
function secondConnection(): Connection
{
    config(['database.connections.mysql_second' => config('database.connections.mysql')]);

    return DB::connection('mysql_second');
}
