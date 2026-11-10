<?php

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');
});

it('documents the item types of admin lists', function (string $path, string $schema) {
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath(
            "paths.{$path}.get.responses.200.content.application/json.schema.properties.data.items.\$ref",
            "#/components/schemas/{$schema}",
        );
})->with([
    ['/admin/users', 'UserResource'],
    ['/admin/events', 'AdminEventResource'],
]);
