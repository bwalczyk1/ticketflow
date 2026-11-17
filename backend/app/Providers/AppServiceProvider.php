<?php

namespace App\Providers;

use Dedoc\Scramble\Configuration\OperationTransformers;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
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
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        $this->documentApiSecurity();
        $this->logFailedJobs();
    }

    private function failOnInvalidConfig(): void
    {
        if ($this->app->runningConsoleCommand(self::SETUP_COMMANDS)) {
            return;
        }

        $validator = Validator::make(
            [
                'app_key' => config('app.key'),
                'payment_gateway' => config('ticketflow.payment_gateway'),
                'fake_failure_rate' => config('ticketflow.fake_failure_rate'),
            ],
            [
                'app_key' => ['required'],
                'payment_gateway' => ['required', 'in:fake'],
                'fake_failure_rate' => ['required', 'numeric', 'between:0,1'],
            ],
            attributes: [
                'app_key' => 'APP_KEY',
                'payment_gateway' => 'PAYMENT_GATEWAY',
                'fake_failure_rate' => 'FAKE_PAYMENT_FAILURE_RATE',
            ],
        );

        if ($validator->fails()) {
            throw new RuntimeException("Invalid configuration:\n- ".implode("\n- ", $validator->errors()->all()));
        }
    }

    /**
     * Horizon keeps every failed job with its payload and exception, where it can be retried by hand.
     * The log line names the job, its ids and the exception class only: exception messages can
     * carry query bindings or e-mail addresses.
     */
    private function logFailedJobs(): void
    {
        Queue::failing(function (JobFailed $event) {
            Log::error('Queued job failed', [
                'job' => $event->job->resolveName(),
                'uuid' => $event->job->uuid(),
                ...self::failedJobIds($event->job),
                'error' => $event->exception::class,
            ]);
        });
    }

    /**
     * The integer ids of the job, or of the event a queued listener got, such as orderId.
     *
     * @return array<string, int>
     */
    private static function failedJobIds(Job $job): array
    {
        $command = unserialize($job->payload()['data']['command'] ?? 'N;');
        $subject = $command instanceof CallQueuedListener ? ($command->data[0] ?? null) : $command;

        if (! is_object($subject)) {
            return [];
        }

        return array_filter(
            get_object_vars($subject),
            fn (mixed $value, string $name) => is_int($value) && str_ends_with($name, 'Id'),
            ARRAY_FILTER_USE_BOTH,
        );
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
