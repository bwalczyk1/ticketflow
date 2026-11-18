<?php

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');
});

/**
 * @return list<string>
 */
function specErrorCodes(array $spec, string $method, string $path, int $status): array
{
    $schema = $spec['paths'][$path][$method]['responses'][$status]['content']['application/json']['schema'] ?? [];
    $variants = $schema['anyOf'] ?? [$schema];

    return collect($variants)
        ->flatMap(fn (array $variant) => $variant['properties']['error']['properties']['code']['enum'] ?? [])
        ->all();
}

/**
 * @return Collection<int, RouteDefinition>
 */
function apiRoutes(): Collection
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => str_starts_with($route->uri(), 'api/'));
}

/**
 * @return list<string>
 */
function routeMethods(RouteDefinition $route): array
{
    return array_values(array_map('strtolower', array_diff($route->methods(), ['HEAD'])));
}

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

it('documents every API route', function () {
    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    $routes = apiRoutes()->flatMap(fn (RouteDefinition $route) => array_map(
        fn (string $method) => $method.' '.substr($route->uri(), 3),
        routeMethods($route),
    ));

    $documented = collect($spec['paths'])->flatMap(fn (array $operations, string $path) => array_map(
        fn (string $method) => "{$method} {$path}",
        array_keys($operations),
    ));

    expect($documented->sort()->values()->all())->toBe($routes->sort()->values()->all());
});

it('documents 403 on every route behind a role check', function () {
    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    $protected = apiRoutes()->filter(fn (RouteDefinition $route) => collect($route->gatherMiddleware())
        ->contains(fn (mixed $middleware) => is_string($middleware) && str_starts_with($middleware, 'role:')));

    expect($protected)->not->toBeEmpty();

    foreach ($protected as $route) {
        foreach (routeMethods($route) as $method) {
            expect(specErrorCodes($spec, $method, substr($route->uri(), 3), 403))->toBe(['FORBIDDEN'], "{$method} {$route->uri()}");
        }
    }
});

it('documents INVALID_JSON next to validation errors on requests with a body', function (string $method, string $path, array $codes) {
    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    expect(specErrorCodes($spec, $method, $path, 400))->toEqualCanonicalizing($codes);
})->with([
    ['post', '/auth/register', ['VALIDATION_ERROR', 'INVALID_JSON']],
    ['patch', '/organizer/events/{id}', ['VALIDATION_ERROR', 'INVALID_JSON']],
    ['post', '/orders/{id}/pay', ['INVALID_JSON']],
]);

it('documents the errors of auth and role changes', function (string $method, string $path, int $status, string $code) {
    $spec = $this->getJson('/docs/api.json')->assertOk()->json();

    expect(specErrorCodes($spec, $method, $path, $status))->toContain($code);
})->with([
    ['post', '/auth/login', 401, 'INVALID_CREDENTIALS'],
    ['post', '/auth/register', 409, 'EMAIL_TAKEN'],
    ['patch', '/admin/users/{id}/role', 404, 'NOT_FOUND'],
    ['patch', '/admin/users/{id}/role', 409, 'OWN_ADMIN_ROLE_PROTECTED'],
]);
