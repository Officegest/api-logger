<?php

declare(strict_types=1);

namespace OfficegestApiLogger;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OfficegestApiLogger\DataObjects\Data;
use OfficegestApiLogger\Exceptions\ConfigurationException;
use Throwable;

use function in_array;

final class OfficegestApiLogger
{
    public const VERSION = '1.0';

    /**
     * Application-supplied callback that returns extra fields to attach to
     * every log payload under the `context` key. Invoked inside `log()` and
     * wrapped in a try/catch so a faulty resolver never escalates to the
     * caller. Pass `null` to clear (useful in tests and Octane resets).
     */
    private static ?Closure $contextResolver = null;

    /**
     * Register a callback whose return array is merged into the payload
     * under the `context` key. The callback runs on every `log()` call,
     * receives no arguments, and must return an array keyed by string.
     * Non-array returns and thrown exceptions are silently discarded so
     * the logger remains best-effort.
     *
     * Example:
     *   OfficegestApiLogger::resolveContextUsing(fn () => [
     *       'tenant_id' => tenant()?->id,
     *   ]);
     */
    public static function resolveContextUsing(?Closure $callback): void
    {
        self::$contextResolver = $callback;
    }

    /**
     * Application-supplied factory that returns a pre-configured Elasticsearch
     * client. Primarily an extension point for host apps that need custom
     * timeouts, retry policies, or a drop-in replacement of the ES client; also
     * lets tests inject a client that throws a specific exception without
     * relying on overload mocks. When `null`, the default builder below is used.
     */
    private static ?Closure $clientBuilderFactory = null;

    /**
     * Register a callback that returns a built `\Elastic\Elasticsearch\Client`.
     * Pass `null` to reset to the default builder (useful in tests and Octane
     * resets).
     */
    public static function useClientBuilderFactory(?Closure $callback): void
    {
        self::$clientBuilderFactory = $callback;
    }

    /**
     * Send request and response payload to OfficegestApiLogger for processing.
     *
     * @throws ConfigurationException
     */
    public static function log(Data $data, ?string $projectId = null): void
    {
        $appEnvironment = config('app.env', 'unknownEnvironment');
        $ignoredEnvironments = config('officegest-api-logger.ignored_environments', '');
        $ignored = explode(',', $ignoredEnvironments);

        // Check if the application environment exists in the ignored environments.
        if (in_array($appEnvironment, $ignored, true)) {
            return;
        }

        $circuitEnabled = (bool) config('officegest-api-logger-config.circuit_breaker.enabled', true);
        $circuitKey = (string) config('officegest-api-logger-config.circuit_breaker.key', 'officegest-api-logger:circuit:open');
        $circuitTtl = (int) config('officegest-api-logger-config.circuit_breaker.ttl', 120);
        $logChannel = config('officegest-api-logger-config.circuit_breaker.log_channel');

        if ($circuitEnabled) {
            try {
                if (Cache::has($circuitKey)) {
                    return;
                }
            } catch (Throwable) {
                // Cache store unavailable — fail open and let the request attempt Elasticsearch.
            }
        }

        $payload = array_merge(
            [
                'version' => self::VERSION,
                'sdk' => 'laravel',
                'user' => auth()->user()->username ?? null,
                'timestamp' => date('Y-m-d H:i:s.v'),
            ],
            [
                'context' => self::resolveContext(),
                'data' => $data->__toArray(),
            ],
        );

        $connectTimeout = (float) config('officegest-api-logger-config.elastic.connect_timeout', 1.0);
        $timeout = (float) config('officegest-api-logger-config.elastic.timeout', 1.5);

        try {
            $client = self::$clientBuilderFactory !== null
                ? (self::$clientBuilderFactory)()
                : (function () use ($timeout, $connectTimeout): \Elastic\Elasticsearch\Client {
                    $username = config('officegest-api-logger-config.username');
                    $password = config('officegest-api-logger-config.password');

                    $clientBuilder = \Elastic\Elasticsearch\ClientBuilder::create()
                        ->setHosts([config('officegest-api-logger-config.host')])
                        ->setSSLVerification(false)
                        ->setRetries(0)
                        ->setHttpClientOptions([
                            'timeout' => $timeout,
                            'connect_timeout' => $connectTimeout,
                        ]);

                    if (!empty($username) && !empty($password)) {
                        $clientBuilder->setBasicAuthentication($username, $password);
                    }

                    return $clientBuilder->build();
                })();

            $client->index([
                'index' => config('officegest-api-logger-config.index') . '_' . date('Ymd'),
                'body' => json_encode($payload),
            ]);
        } catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
            $logger = $logChannel !== null ? Log::channel((string) $logChannel) : Log::driver();
            $logger->warning('OfficegestApiLogger document rejected', [
                'error' => $e->getMessage(),
                'host' => config('officegest-api-logger-config.host'),
                'url' => $data->request->url ?? null,
                'trace_id' => $data->request->headers['x-officegest-api-logger-trace-id'] ?? null,
            ]);
        } catch (Throwable $e) {
            if ($circuitEnabled) {
                try {
                    Cache::put($circuitKey, 1, $circuitTtl);
                } catch (Throwable) {
                    // cache store also broken — nothing else we can do here
                }
            }

            $logger = $logChannel !== null ? Log::channel((string) $logChannel) : Log::driver();
            $logger->warning('OfficegestApiLogger circuit opened', [
                'error' => $e->getMessage(),
                'host' => config('officegest-api-logger-config.host'),
                'ttl' => $circuitTtl,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveContext(): array
    {
        if (self::$contextResolver === null) {
            return [];
        }

        try {
            $context = (self::$contextResolver)();
        } catch (Throwable) {
            return [];
        }

        return is_array($context) ? $context : [];
    }
}
