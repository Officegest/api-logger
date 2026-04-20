<?php

declare(strict_types=1);

namespace OfficegestApiLogger\Tests;

use OfficegestApiLogger\OfficegestApiLoggerServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            OfficegestApiLoggerServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function defineEnvironment($app): void
    {
        // Environment not in ignored_environments so log() runs end-to-end.
        $app['config']->set('app.env', 'production');

        // Point to a closed port so any real HTTP attempt fails fast and triggers the circuit.
        $app['config']->set('officegest-api-logger-config.host', 'http://127.0.0.1:65535');
        $app['config']->set('officegest-api-logger-config.index', 'test-index');

        // Tight timeouts keep tests fast even if the OS resolves the refused connection slowly.
        $app['config']->set('officegest-api-logger-config.elastic.connect_timeout', 0.1);
        $app['config']->set('officegest-api-logger-config.elastic.timeout', 0.2);

        $app['config']->set('officegest-api-logger-config.circuit_breaker.enabled', true);
        $app['config']->set('officegest-api-logger-config.circuit_breaker.key', 'officegest-api-logger:circuit:open');
        $app['config']->set('officegest-api-logger-config.circuit_breaker.ttl', 120);
        $app['config']->set('officegest-api-logger-config.circuit_breaker.log_channel', null);
    }
}
