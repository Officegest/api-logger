<?php

declare(strict_types=1);

namespace OfficegestApiLogger\Factories;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OfficegestApiLogger\DataObjects\Data;
use OfficegestApiLogger\DataObjects\Error;
use OfficegestApiLogger\DataObjects\Language;
use OfficegestApiLogger\DataObjects\OS;
use OfficegestApiLogger\DataObjects\Request as RequestObject;
use OfficegestApiLogger\DataObjects\Response as ResponseObject;
use OfficegestApiLogger\DataObjects\Server;
use OfficegestApiLogger\Http\Method;
use OfficegestApiLogger\Masks\FieldMasker;
use OfficegestApiLogger\Support\PHP;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class DataFactory
{
    public function __construct(
        private readonly FieldMasker $masker,
    )
    {
    }

    public function make(Request $request, JsonResponse|Response|SymfonyResponse $response, float|int $loadTime): Data
    {
        $php = new PHP();

        $errors = [];

        try {
            $responseBody = $this->masker->mask(
                (array)json_decode(
                    (string)$response->getContent(),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                ),
            );
        } catch (Throwable) {
            $responseBody = '{}';
        }

        if (!empty($response->exception)) {
            $errors[] = new Error(
                'onException',
                'UNHANDLED_EXCEPTION',
                $response->exception->getMessage(),
                $response->exception->getFile(),
                $response->exception->getLine(),
            );
        }

        return new Data(
            new Server(
                (string)$request->server('SERVER_ADDR'),
                (string)config('app.timezone'),
                (string)$request->server('SERVER_SOFTWARE'),
                (string)$request->server('SERVER_SIGNATURE'),
                (string)$request->server('SERVER_PROTOCOL'),
                new OS(
                    php_uname('s'),
                    php_uname('r'),
                    php_uname('m'),
                ),
                (string)$request->server('HTTP_ACCEPT_ENCODING'),
                (string)gethostname(),
            ),
            new Language(
                'php',
                PHP_VERSION,
                $php->get(
                    'expose_php',
                ),
                $php->get(
                    'display_errors',
                ),
            ),
            new RequestObject(
                Carbon::now('UTC')->format('Y-m-d H:i:s.v'),
                (string)$request->ip(),
                $request->fullUrl(),
                $request->route()?->toSymfonyRoute()->getPath(),
                (string)$request->userAgent(),
                Method::from(
                    $request->method(),
                ),
                $this->masker->mask(
                    collect($request->headers->all())->transform(
                    /* @phpstan-ignore-next-line */
                        fn($item) => collect($item)->first(),
                    )->toArray(),
                ),
                $this->masker->mask(
                    $request->all(),
                ),
                $this->masker->mask(
                    $request->all(),
                ),
            ),
            new ResponseObject(
                $this->masker->mask(
                    collect($response->headers->all())->transform(
                    /* @phpstan-ignore-next-line */
                        fn($item) => collect($item)->first(),
                    )->toArray(),
                ),
                $response->getStatusCode(),
                \strlen((string)$response->getContent()),
                $loadTime,
                $responseBody,
            ),
            $errors,
        );
    }
}
