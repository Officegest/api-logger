## Officegest Api Logger

This is a laravel package to save logs from api.

## Installation

    composer require officegest/api-logger

Publish the config file for this package. This will add the file `config/officegest-api-logger-config.php`, where you
can configure this package.

    $ php artisan vendor:publish --tag=officegest-api-logger-config

You need add this variables to your .env

    API_LOGGER_ELASTIC_HOST="your_elastic_url:port"
    API_LOGGER_ELASTIC_LOGS_INDEX="your_index_name"
    
    ## Optional
    #API_LOGGER_ELASTIC_USERNAME="your_user"
    #API_LOGGER_ELASTIC_PASSWORD="your_password"


## Usage

Add middleware alias to $middlewareAliases

    //app/Http/Kernel.php (l10)
    'logger' => \OfficegestApiLogger\Middlewares\OfficegestApiLoggerMiddleware::class,

Add middleware at routes you want log to elasticsearch

    /*
    |--------------------------------------------------------------------------
    | Example add middleware to group of endpoints
    |--------------------------------------------------------------------------
    */
    Route::middleware('logger')->controller(YourController::class)
        ->name('yourname.')
        ->prefix('yourprefix')
        ->group(function () {
            ...
        });

### Attaching custom fields to every log (e.g. tenant_id)

Each host application can register a context resolver in a service provider.
The callback runs on every log call and its return array is merged into the
payload under a new top-level `context` key. Non-array returns and thrown
exceptions are silently discarded so the logger stays best-effort.

    //app/Providers/AppServiceProvider.php
    use OfficegestApiLogger\OfficegestApiLogger;

    public function boot(): void
    {
        OfficegestApiLogger::resolveContextUsing(fn () => [
            'tenant_id' => tenant()?->id,
            'correlation_id' => request()->header('X-Correlation-Id'),
        ]);
    }

The resulting document indexed in Elasticsearch will contain:

    { "version": "1.0", "user": "...", "context": { "tenant_id": 42, "correlation_id": "..." }, "data": { ... } }

Pass `null` to clear the resolver (useful in tests and between Octane
requests if your application rebinds it per worker).

## Security

If you discover any security related issues, please email suporte@guisoft.net or use issues of this repo.

## Credits

- [Officegest.com][link-author]
- [Guisoft.net][link-guisoft]
- [All Contributors][link-contributors]

[link-author]: https://officegest.com

[link-guisoft]: https://guisoft.net

[link-contributors]: ../../contributors
