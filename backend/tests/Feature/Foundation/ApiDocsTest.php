<?php

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');
});

it('serves the documentation UI', function () {
    $this->get('/docs/api')->assertOk();
});

it('describes bearer authentication in the specification', function () {
    $response = $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('components.securitySchemes.http.type', 'http')
        ->assertJsonPath('components.securitySchemes.http.scheme', 'bearer');

    expect($response->json('openapi'))->toStartWith('3.1');
});

it('marks routes without the auth middleware as public', function () {
    $this->getJson('/docs/api.json')
        ->assertOk()
        ->assertJsonPath('paths./health.get.security', []);
});
