<?php

declare(strict_types=1);

namespace OfficegestApiLogger\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OfficegestApiLogger\DataObjects\Data;
use OfficegestApiLogger\Factories\DataFactory;
use OfficegestApiLogger\OfficegestApiLogger;
use OfficegestApiLogger\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;

final class ContextResolverTest extends TestCase
{
    private Data $data;

    protected function setUp(): void
    {
        parent::setUp();

        OfficegestApiLogger::resolveContextUsing(null);

        $this->data = app(DataFactory::class)->make(
            request: Request::create('http://example.com/api/test', 'GET'),
            response: new Response('{}', 200),
            loadTime: 0.05,
        );
    }

    protected function tearDown(): void
    {
        OfficegestApiLogger::resolveContextUsing(null);

        parent::tearDown();
    }

    #[Test]
    public function returns_empty_array_when_no_resolver_registered(): void
    {
        self::assertSame([], $this->invokeResolveContext());
    }

    #[Test]
    public function returns_resolver_output_when_registered(): void
    {
        OfficegestApiLogger::resolveContextUsing(static fn(): array => [
            'tenant_id' => 42,
            'correlation_id' => 'abc-123',
        ]);

        self::assertSame(
            ['tenant_id' => 42, 'correlation_id' => 'abc-123'],
            $this->invokeResolveContext(),
        );
    }

    #[Test]
    public function swallows_resolver_exceptions(): void
    {
        OfficegestApiLogger::resolveContextUsing(static function (): array {
            throw new \RuntimeException('resolver blew up');
        });

        // Must not bubble the exception and must return an empty array so the
        // caller can still build a valid payload.
        self::assertSame([], $this->invokeResolveContext());
    }

    #[Test]
    public function coerces_non_array_return_to_empty_array(): void
    {
        // Developers may accidentally return a string or null from a resolver;
        // the logger should stay defensive rather than crash on json_encode.
        /* @phpstan-ignore-next-line */
        OfficegestApiLogger::resolveContextUsing(static fn(): string => 'not-an-array');

        self::assertSame([], $this->invokeResolveContext());
    }

    #[Test]
    public function null_clears_a_previously_registered_resolver(): void
    {
        OfficegestApiLogger::resolveContextUsing(static fn(): array => ['tenant_id' => 1]);
        OfficegestApiLogger::resolveContextUsing(null);

        self::assertSame([], $this->invokeResolveContext());
    }

    #[Test]
    public function end_to_end_log_call_still_succeeds_with_a_resolver(): void
    {
        OfficegestApiLogger::resolveContextUsing(static fn(): array => ['tenant_id' => 7]);

        Cache::shouldReceive('has')->once()->andReturn(false);
        Cache::shouldReceive('put')->once();

        Log::shouldReceive('driver')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        // The ES host points at a closed port (see TestCase::defineEnvironment)
        // so the call fails fast and the circuit opens — exactly the same
        // happy-path assertions as the existing CircuitBreakerTest, proving
        // the context resolver does not perturb the failure mode.
        OfficegestApiLogger::log($this->data);
    }

    private function invokeResolveContext(): array
    {
        $method = new ReflectionMethod(OfficegestApiLogger::class, 'resolveContext');

        return $method->invoke(null);
    }
}
