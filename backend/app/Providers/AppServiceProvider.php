<?php

namespace App\Providers;

use App\Support\ConfigValidator;
use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * These run while the configuration is still being prepared, for example during composer install.
     */
    private const SETUP_COMMANDS = ['key:generate', 'package:discover'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->failOnInvalidConfig();

        JsonResource::withoutWrapping();
        Model::preventLazyLoading(! $this->app->isProduction());

        $this->documentApiSecurity();
    }

    private function failOnInvalidConfig(): void
    {
        if ($this->app->runningConsoleCommand(self::SETUP_COMMANDS)) {
            return;
        }

        $errors = ConfigValidator::errors([
            'app_key' => config('app.key'),
            'payment_gateway' => config('ticketflow.payment_gateway'),
            'fake_failure_rate' => config('ticketflow.fake_failure_rate'),
            'stripe_secret' => config('ticketflow.stripe_secret'),
        ]);

        if ($errors !== []) {
            throw new RuntimeException("Invalid configuration:\n- ".implode("\n- ", $errors));
        }
    }

    /**
     * Bearer tokens are what "Try it" in /docs/api can send; the SPA itself uses the session cookie.
     * A route without the auth middleware is documented as public.
     */
    private function documentApiSecurity(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            })
            ->withOperationTransformers(function (OperationTransformers $transformers) {
                $transformers->prepend(function (Operation $operation, RouteInfo $routeInfo) {
                    $protected = collect($routeInfo->route->gatherMiddleware())
                        ->contains(fn (mixed $middleware) => is_string($middleware) && Str::is(['auth', 'auth:*'], $middleware));

                    if (! $protected) {
                        $operation->security = [];
                    }
                });
            });
    }
}
