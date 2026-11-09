<?php

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

it('spots a select list that fetches every column', function (string $sql) {
    expect(TestCase::selectsAllColumns($sql))->toBeTrue();
})->with([
    'select * from `users` where `id` = ? limit 1',
    'select `events`.* from `events` inner join `ticket_types` on `ticket_types`.`event_id` = `events`.`id`',
    'select `orders`.*, `users`.`email` from `orders` inner join `users` on `users`.`id` = `orders`.`user_id`',
    'select distinct * from `tickets`',
    'select `id` from `events` where `id` in (select * from `ticket_types`)',
]);

it('accepts explicit columns, counts and exists checks', function (string $sql) {
    expect(TestCase::selectsAllColumns($sql))->toBeFalse();
})->with([
    'select `id`, `email` from `users` where `id` = ? limit 1',
    'select count(*) as aggregate from `users`',
    'select exists(select * from `users` where `email` = ?) as `exists`',
    'select `id` from `events` where exists (select * from `ticket_types` where `ticket_types`.`event_id` = `events`.`id`)',
    'select `events`.`id`, (select count(*) from `ticket_types` where `ticket_types`.`event_id` = `events`.`id`) as `ticket_types_count` from `events`',
]);

it('blames a query on the first caller outside the framework query layers', function () {
    $framework = base_path('vendor/laravel/framework/src/Illuminate');

    $fromApp = [
        ['file' => "{$framework}/Events/Dispatcher.php"],
        ['file' => "{$framework}/Database/Connection.php"],
        ['file' => "{$framework}/Database/Eloquent/Builder.php"],
        ['file' => base_path('app/Http/Controllers/Admin/UserController.php')],
        ['file' => "{$framework}/Routing/Controller.php"],
    ];

    $fromFramework = [
        ['file' => "{$framework}/Database/Eloquent/Builder.php"],
        ['file' => "{$framework}/Auth/EloquentUserProvider.php"],
        ['file' => base_path('app/Http/Middleware/RequestId.php')],
    ];

    $fromTest = [
        ['file' => "{$framework}/Database/Query/Builder.php"],
        ['file' => base_path('tests/Feature/Foundation/ExplicitColumnsGuardTest.php')],
    ];

    expect(TestCase::issuedFromApp($fromApp))->toBeTrue()
        ->and(TestCase::issuedFromApp($fromFramework))->toBeFalse()
        ->and(TestCase::issuedFromApp($fromTest))->toBeFalse();
});

it('lets test code select every column and sees framework files by their real paths', function () {
    $files = [];

    DB::listen(function () use (&$files) {
        $files = array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'file');
    });

    DB::table('users')->get();

    expect($files[0])->toStartWith(base_path('vendor/laravel/framework/src/Illuminate/Events/'))
        ->and($files)->toContain(__FILE__);
});
