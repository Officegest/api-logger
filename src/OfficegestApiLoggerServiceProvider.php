<?php

declare(strict_types=1);

namespace OfficegestApiLogger;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Octane\Events\RequestReceived;
use OfficegestApiLogger\Masks\FieldMasker;
use function config;

final class OfficegestApiLoggerServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/officegest-api-logger-config.php' => config_path('officegest-api-logger-config.php'),
            ], 'officegest-api-logger-config');
        }

        if ($this->httpServerIsOctane()) {
            /**
             * @var Dispatcher $events
             */
            $events = $this->app->make(
                abstract: Dispatcher::class,
            );

            $uuid = Str::uuid()->toString();
            $this->app->bind('officegest-api-logger-identifier', fn() => $uuid);

            $events->listen(RequestReceived::class, function () use ($uuid) {
                if (config('octane.server') === 'roadrunner') {
                    Cache::put($uuid, microtime(true));

                    return;
                }

                Cache::store('octane')->put($uuid, microtime(true));
            });
        }

        AboutCommand::add(
            section: 'OfficegestApiLogger',
            data: static fn(): array => [
                'Version' => OfficegestApiLogger::VERSION,
                'host' => config('officegest-api-logger.host'),
                'index' => config('officegest-api-logger.index'),
                'Ignored Environments' => config('officegest-api-logger.ignore_environments'),
            ],
        );
    }

    /**
     * Determine if server is running Octane
     */
    private function httpServerIsOctane(): bool
    {
        return isset($_ENV['OCTANE_DATABASE_SESSION_TTL']);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../config/officegest-api-logger-config.php',
            key: 'officegest-api-logger-config',
        );

        $fields = config('officegest-api-logger.masked_fields');

        $this->app->singleton(
            abstract: Masks\FieldMasker::class,
            concrete: fn() => new Masks\FieldMasker(
                fields: is_array($fields) ? $fields : [],
            ),
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            FieldMasker::class,
        ];
    }
}
