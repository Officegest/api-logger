<?php

return [
    /*
     * An override while debugging.
     */
    'host' => env('API_LOGGER_ELASTIC_HOST', null),

    /*
     * A valid OfficegestApiLogger project ID. Create your first project on https://officegest.com/
     */
    'index' => env('API_LOGGER_ELASTIC_LOGS_INDEX', null),

    /*
     * Define which environments should OfficegestApiLogger ignore and not monitor
     */
    'ignored_environments' => env('OFFICEGEST_API_LOGGER_IGNORED_ENV', 'dev,test,testing'),

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
