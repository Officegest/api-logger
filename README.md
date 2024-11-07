## Officegest Api Logger

This is a laravel package to save logs from api.

## Installation

    composer require officegest/api-logger

Publish the config file for this package. This will add the file `config/officegest-api-logger-config.php`, where you
can configure this package.

    $ php artisan vendor:publish --tag=officegest-api-logger-config

You need add this variables to your .env

    API_LOGGER_ELASTIC_HOST=true
    API_LOGGER_ELASTIC_LOGS_INDEX="your_officegest_url"

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

## Security

If you discover any security related issues, please email suporte@guisoft.net or use issues of this repo.

## Credits

- [Officegest.com][link-author]
- [Guisoft.net][link-guisoft]
- [All Contributors][link-contributors]

[link-author]: https://officegest.com

[link-guisoft]: https://guisoft.net

[link-contributors]: ../../contributors
