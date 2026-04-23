<?php

declare(strict_types=1);

namespace OfficegestApiLogger\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OfficegestApiLogger\Factories\DataFactory;
use OfficegestApiLogger\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class DataFactoryTest extends TestCase
{
    #[Test]
    public function falls_back_to_empty_array_when_response_body_is_not_valid_json(): void
    {
        // Elasticsearch maps `data.response.body` as a `flattened` field and rejects
        // VALUE_STRING tokens with a parsing_exception. The fallback must therefore stay an array.
        $data = app(DataFactory::class)->make(
            request: Request::create('http://example.com/api/test', 'GET'),
            response: new Response('<html>not json</html>', 500),
            loadTime: 0.01,
        );

        self::assertIsArray($data->response->body);
        self::assertSame([], $data->response->body);
    }

    #[Test]
    public function decodes_response_body_into_array_when_valid_json(): void
    {
        $data = app(DataFactory::class)->make(
            request: Request::create('http://example.com/api/test', 'GET'),
            response: new Response('{"ok":true,"value":42}', 200),
            loadTime: 0.01,
        );

        self::assertIsArray($data->response->body);
        self::assertSame(['ok' => true, 'value' => 42], $data->response->body);
    }

    #[Test]
    public function falls_back_to_empty_array_when_response_body_is_empty(): void
    {
        $data = app(DataFactory::class)->make(
            request: Request::create('http://example.com/api/test', 'GET'),
            response: new Response('', 204),
            loadTime: 0.01,
        );

        self::assertIsArray($data->response->body);
        self::assertSame([], $data->response->body);
    }
}
