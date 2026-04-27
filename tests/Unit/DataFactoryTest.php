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

    #[Test]
    public function request_payload_does_not_duplicate_body_under_a_raw_key(): void
    {
        // 2.0.0 dropped `data.request.raw`. Before, body and raw were both populated
        // from the same `$request->all()`, doubling the payload sent to Elasticsearch
        // and the work done by the masker for zero added value.
        $data = app(DataFactory::class)->make(
            request: Request::create(
                uri: 'http://example.com/api/test',
                method: 'POST',
                parameters: ['username' => 'alice'],
            ),
            response: new Response('{}', 200),
            loadTime: 0.01,
        );

        $payload = $data->request->__toArray();

        self::assertArrayNotHasKey('raw', $payload);
        self::assertArrayHasKey('body', $payload);
        self::assertSame('alice', $payload['body']['username']);
    }

    #[Test]
    public function masks_sensitive_request_body_fields_from_config(): void
    {
        // Until 2.0.0 the ServiceProvider read masked_fields from the wrong config
        // key ('officegest-api-logger.masked_fields' instead of '...-config.masked_fields'),
        // leaving the FieldMasker initialised with []. As a result every login request
        // shipped passwords, card numbers, ssns and api keys to Elasticsearch in plaintext —
        // only the hardcoded `authorization` and `x-api-key` headers were masked. This test
        // pins the expected behaviour: every entry in config.masked_fields is masked end to end.
        $data = app(DataFactory::class)->make(
            request: Request::create(
                uri: 'http://example.com/api/auth/login',
                method: 'POST',
                parameters: [
                    'username' => 'alice',
                    'password' => 'super-secret',
                    'card_number' => '4111111111111111',
                    'ssn' => '123-45-6789',
                    'api_key' => 'sk_live_abcdef',
                ],
            ),
            response: new Response('{}', 200),
            loadTime: 0.01,
        );

        $body = $data->request->body;

        // Username is not in the mask list and stays untouched.
        self::assertSame('alice', $body['username']);

        // Each masked field is replaced character-for-character with `*`.
        self::assertSame(str_repeat('*', strlen('super-secret')), $body['password']);
        self::assertSame(str_repeat('*', strlen('4111111111111111')), $body['card_number']);
        self::assertSame(str_repeat('*', strlen('123-45-6789')), $body['ssn']);
        self::assertSame(str_repeat('*', strlen('sk_live_abcdef')), $body['api_key']);
    }
}
