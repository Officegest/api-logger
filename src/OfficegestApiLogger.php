<?php

declare(strict_types=1);

namespace OfficegestApiLogger;

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
                'data' => $data->__toArray(),
            ],
        );

        $connectTimeout = (float) config('officegest-api-logger-config.elastic.connect_timeout', 1.0);
        $timeout = (float) config('officegest-api-logger-config.elastic.timeout', 1.5);

        try {
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

            $client = $clientBuilder->build();

            $client->index([
                'index' => config('officegest-api-logger-config.index') . '_' . date('Ymd'),
                'body' => json_encode($payload),
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
}
