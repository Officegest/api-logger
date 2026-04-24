<?php

declare(strict_types=1);

namespace OfficegestApiLogger\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use OfficegestApiLogger\DataObjects\Data;
use OfficegestApiLogger\Factories\DataFactory;
use OfficegestApiLogger\OfficegestApiLogger;
use OfficegestApiLogger\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class CircuitBreakerTest extends TestCase
{
    private Data $data;

    protected function setUp(): void
    {
        parent::setUp();

        // Build a real Data payload through the factory so we exercise the production serialization path.
        $this->data = app(DataFactory::class)->make(
            request: Request::create('http://example.com/api/test', 'GET'),
            response: new Response('{}', 200),
            loadTime: 0.05,
        );
    }

    #[Test]
    public function short_circuits_when_breaker_is_open(): void
    {
        Cache::shouldReceive('has')
            ->once()
            ->with('officegest-api-logger:circuit:open')
            ->andReturn(true);

        Cache::shouldReceive('put')->never();
        Log::shouldReceive('warning')->never();
        Log::shouldReceive('channel')->never();
        Log::shouldReceive('driver')->never();

        OfficegestApiLogger::log($this->data);
    }

    #[Test]
    public function opens_circuit_when_elasticsearch_fails(): void
    {
        Cache::shouldReceive('has')->once()->andReturn(false);
        Cache::shouldReceive('put')
            ->once()
            ->with('officegest-api-logger:circuit:open', 1, 120);

        Log::shouldReceive('driver')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->with('OfficegestApiLogger circuit opened', Mockery::on(
                static fn ($context): bool => is_array($context)
                    && array_key_exists('error', $context)
                    && array_key_exists('host', $context)
                    && array_key_exists('ttl', $context),
            ));

        OfficegestApiLogger::log($this->data);
    }

    #[Test]
    public function writes_to_configured_log_channel(): void
    {
        config(['officegest-api-logger-config.circuit_breaker.log_channel' => 'elastic']);

        Cache::shouldReceive('has')->once()->andReturn(false);
        Cache::shouldReceive('put')->once();

        Log::shouldReceive('channel')
            ->once()
            ->with('elastic')
            ->andReturnSelf();

        Log::shouldReceive('warning')->once();

        OfficegestApiLogger::log($this->data);
    }

    #[Test]
    public function bypasses_cache_when_circuit_breaker_disabled(): void
    {
        config(['officegest-api-logger-config.circuit_breaker.enabled' => false]);

        Cache::shouldReceive('has')->never();
        Cache::shouldReceive('put')->never();

        Log::shouldReceive('driver')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        OfficegestApiLogger::log($this->data);
    }

    #[Test]
    public function skips_work_when_environment_is_ignored(): void
    {
        // Note: the package reads `officegest-api-logger.ignored_environments` (not
        // `officegest-api-logger-config.ignored_environments`). We set the exact key the
        // implementation uses to avoid testing around a pre-existing config-key mismatch.
        config([
            'app.env' => 'testing',
            'officegest-api-logger.ignored_environments' => 'testing',
        ]);

        Cache::shouldReceive('has')->never();
        Cache::shouldReceive('put')->never();
        Log::shouldReceive('warning')->never();

        OfficegestApiLogger::log($this->data);
    }

    #[Test]
    public function survives_cache_store_failures(): void
    {
        Cache::shouldReceive('has')
            ->once()
            ->andThrow(new \RuntimeException('cache store down'));

        // Even without the circuit check, the ES call will still fail against the closed port,
        // and the catch branch tries to persist the flag — which also throws — and must not escape.
        Cache::shouldReceive('put')
            ->once()
            ->andThrow(new \RuntimeException('cache store down'));

        Log::shouldReceive('driver')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        OfficegestApiLogger::log($this->data);
    }

    #[Test]
    public function does_not_open_circuit_on_client_response_exception(): void
    {
        OfficegestApiLogger::useClientBuilderFactory(function (): \Elastic\Elasticsearch\ClientInterface {
            $client = Mockery::mock(\Elastic\Elasticsearch\ClientInterface::class);
            $client->shouldReceive('index')->andThrow(
                new \Elastic\Elasticsearch\Exception\ClientResponseException('400 Bad Request'),
            );
            return $client;
        });

        Cache::shouldReceive('has')->once()->andReturn(false);
        Cache::shouldReceive('put')->never();

        Log::shouldReceive('driver')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->with('OfficegestApiLogger document rejected', Mockery::on(
                static fn ($context): bool => is_array($context)
                    && array_key_exists('error', $context)
                    && array_key_exists('host', $context)
                    && array_key_exists('url', $context)
                    && array_key_exists('trace_id', $context),
            ));

        OfficegestApiLogger::log($this->data);
    }

    #[Test]
    public function still_opens_circuit_on_server_response_exception(): void
    {
        OfficegestApiLogger::useClientBuilderFactory(function (): \Elastic\Elasticsearch\ClientInterface {
            $client = Mockery::mock(\Elastic\Elasticsearch\ClientInterface::class);
            $client->shouldReceive('index')->andThrow(
                new \Elastic\Elasticsearch\Exception\ServerResponseException('500 Internal Server Error'),
            );
            return $client;
        });

        Cache::shouldReceive('has')->once()->andReturn(false);
        Cache::shouldReceive('put')
            ->once()
            ->with('officegest-api-logger:circuit:open', 1, 120);

        Log::shouldReceive('driver')->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->with('OfficegestApiLogger circuit opened', Mockery::any());

        OfficegestApiLogger::log($this->data);
    }

    protected function tearDown(): void
    {
        OfficegestApiLogger::useClientBuilderFactory(null);
        parent::tearDown();
    }
}
