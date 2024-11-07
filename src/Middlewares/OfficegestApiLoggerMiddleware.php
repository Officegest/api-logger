<?php

declare(strict_types=1);

namespace OfficegestApiLogger\Middlewares;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OfficegestApiLogger\Exceptions\ConfigurationException;
use OfficegestApiLogger\Factories\DataFactory;
use OfficegestApiLogger\OfficegestApiLogger;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use function config;
use function microtime;

class OfficegestApiLoggerMiddleware
{
    public static float $start = 0.00;

    public function __construct(
        protected readonly DataFactory $factory,
    )
    {
    }

    public function handle(Request $request, Closure $next): Response|JsonResponse|SymfonyResponse
    {
        return $next($request);
    }

    /**
     * @throws ConfigurationException
     */
    public function terminate(Request $request, JsonResponse|Response|SymfonyResponse $response): void
    {
        $config = (array)config('officegest-api-logger-config');

        if ($config['index'] === null || $config['host'] === null) {
            return;
        }

        if (!$request->headers->has('X-OFFICEGEST-API-LOGGER-TRACE-ID')) {
            $request->headers->add([
                'X-OFFICEGEST-API-LOGGER-TRACE-ID' => $id = Str::uuid(),
            ]);
        }

        $response->headers->add([
            'X-OFFICEGEST-API-LOGGER-TRACE-ID' => $request->headers->get('X-OFFICEGEST-API-LOGGER-TRACE-ID'),
        ]);

        if (strlen((string)$response->getContent()) > 2 * 1024 * 1024) {
            if (!app()->environment('production')) {
                Log::error(
                    message: 'Cannot send response over 2MB to OfficegestApiLogger.',
                    context: [
                        'url' => $request->fullUrl(),
                        'date' => now()->toDateTimeString(),
                    ],
                );
            }

            return;
        }

        OfficegestApiLogger::log(
            data: $this->factory->make(
                request: $request,
                response: $response,
                loadTime: microtime(true) - $this->startTime(),
            ),
        );
    }

    private function startTime(): float
    {
        return $_SERVER['REQUEST_TIME_FLOAT']
            ?? (defined('LARAVEL_START') ? LARAVEL_START : null)
            ?? microtime(true);
    }
}
