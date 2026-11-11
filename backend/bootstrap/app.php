<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestId::class);
        $middleware->statefulApi();

        // There is no login page to redirect to, so guests get 401 whatever the Accept header says.
        $middleware->redirectGuestsTo(null);
        $middleware->alias(['role' => EnsureRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport(DomainException::class);
        $exceptions->dontReportWhen(fn (Throwable $e) => ApiExceptionRenderer::isClientConstraintError($e, request()));
        $exceptions->render(new ApiExceptionRenderer);
    })->create();
