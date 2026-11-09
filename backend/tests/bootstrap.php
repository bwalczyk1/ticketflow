<?php

// PHPUnit's force="true" updates only $_ENV and putenv, but Laravel reads $_SERVER first and the
// container environment lives there. Copying the values makes phpunit.xml win over the container.
foreach ($_ENV as $name => $value) {
    $_SERVER[$name] = $value;
}

// The test database never comes from the application environment, so a test run
// cannot wipe the development database.
$database = getenv('TEST_DB_DATABASE') ?: 'ticketflow_test';

putenv("DB_DATABASE={$database}");
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;

require __DIR__.'/../vendor/autoload.php';
