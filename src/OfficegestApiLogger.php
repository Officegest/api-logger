<?php

declare(strict_types=1);

namespace OfficegestApiLogger;

use Illuminate\Support\Facades\Log;
use OfficegestApiLogger\DataObjects\Data;
use OfficegestApiLogger\Exceptions\ConfigurationException;

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

        $data = array_merge(
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

        try {

            $username = config('officegest-api-logger-config.username');
            $password = config('officegest-api-logger-config.password');


           $clientBuilder = \Elastic\Elasticsearch\ClientBuilder::create()
            ->setHosts([config('officegest-api-logger-config.host')])
            ->setSSLVerification(false);

            // Adiciona autenticação apenas se username e password estiverem preenchidos
            if (!empty($username) && !empty($password)) {
                $clientBuilder->setBasicAuthentication($username, $password);
            }

            $client = $clientBuilder->build();

            $client->index([
                'index' => config('officegest-api-logger-config.index') . '_' . date('Ymd'),
                'body' => json_encode($data),
            ]);
        } catch (\Exception $e) {
            if (config('app.debug')) {
                Log::error('OFFICEGEST_API_LOGGER | ' . $e->getMessage());
            }
        }
    }
}
