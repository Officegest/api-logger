<?php

return [
    /*
     * An override while debugging.
     */
    'host' => env('API_LOGGER_ELASTIC_HOST', null),

    /**
     * auth credentials
     */

    'username' => env('API_LOGGER_ELASTIC_USERNAME', null),
    'password' => env('API_LOGGER_ELASTIC_PASSWORD', null),

    /*
     * A valid OfficegestApiLogger project ID. Create your first project on https://officegest.com/
     */
    'index' => env('API_LOGGER_ELASTIC_LOGS_INDEX', null),

    /*
     * Define which environments should OfficegestApiLogger ignore and not monitor
     */
    'ignored_environments' => env('OFFICEGEST_API_LOGGER_IGNORED_ENV', 'dev,test,testing'),

    /*
     * Elasticsearch HTTP client timeouts (seconds). Keep these low so a slow or
     * unreachable Elasticsearch host cannot hold the PHP-FPM worker (and its DB
     * connection) hostage during the middleware `terminate()` step.
     */
    'elastic' => [
        'connect_timeout' => env('API_LOGGER_ELASTIC_CONNECT_TIMEOUT', 1.0),
        'timeout' => env('API_LOGGER_ELASTIC_TIMEOUT', 1.5),
    ],

    /*
     * Circuit breaker. When a request to Elasticsearch fails, a flag is stored
     * in the application cache for `ttl` seconds and every subsequent log
     * attempt short-circuits without touching Elasticsearch, keeping API
     * response times unaffected while the backend is down.
     */
    'circuit_breaker' => [
        'enabled' => env('API_LOGGER_CIRCUIT_ENABLED', true),
        'key' => env('API_LOGGER_CIRCUIT_KEY', 'officegest-api-logger:circuit:open'),
        'ttl' => env('API_LOGGER_CIRCUIT_TTL', 120),
        'log_channel' => env('API_LOGGER_CIRCUIT_LOG_CHANNEL', null),
    ],

    /*
     * Define which fields should be masked before leaving the server
     */
    'masked_fields' => [
        'password',
        'pwd',
        'secret',
        'password_confirmation',
        'cc',
        'card_number',
        'ccv',
        'ssn',
        'credit_score',
        'api_key',
    ],
];
