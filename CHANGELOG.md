# Changelog

2026-04-24 - 1.6.0

    changed Elasticsearch write error handling to split `ClientResponseException` (4xx) from transport/5xx/auth failures; 4xx responses (e.g. `document_parsing_exception` from a mapping conflict) no longer trip the circuit breaker. The breaker exists to protect FPM workers when Elasticsearch is unreachable, not to react to individual rejected documents — previously a single malformed payload (e.g. a frontend sending an object where a scalar was expected, poisoning a `text`-mapped field) silenced observability for all requests for 120s until the breaker reset;
    added a distinct `'OfficegestApiLogger document rejected'` warning emitted on 4xx, carrying `error`, `host`, `url` and `trace_id` so operators can locate the misbehaving client without losing the rest of the log stream. The existing `'OfficegestApiLogger circuit opened'` warning is now emitted only for transport errors, 5xx, auth failures and cache-store failures;
    updated CircuitBreakerTest to assert that `ClientResponseException` leaves the breaker closed and produces the `document rejected` warning, while other throwables still open the breaker;

2026-04-23 - 1.5.0

    added `OfficegestApiLogger::resolveContextUsing(?Closure $callback)` hook so host applications can attach custom fields to every log payload (e.g. `tenant_id`, `correlation_id`) without forking the package. The callback runs on every `log()` call, receives no arguments, and its return array is merged into the payload under a new top-level `context` key. Non-array returns and thrown exceptions are silently discarded so the logger remains best-effort and never escalates to the caller;
    added ContextResolverTest with 6 cases covering empty default, happy path, exception swallowing, non-array coercion, null clearing, and end-to-end compatibility with the circuit breaker;

2026-04-23 - 1.4.1

    fixed DataFactory fallback returning the string `'{}'` when the response body is not valid JSON; Elasticsearch maps `data.response.body` as a `flattened` field and rejected VALUE_STRING tokens with a parsing_exception, opening the circuit breaker on every non-JSON response. Fallback now returns an empty array (`[]`), matching the `flattened` mapping;
    added DataFactoryTest covering the empty-array fallback for non-JSON and empty response bodies, plus the happy path for valid JSON;

2026-04-20 - 1.4.0

    added circuit breaker around Elasticsearch writes; aggressive HTTP timeouts (connect 1s, total 1.5s) and setRetries(0) so an unreachable Elasticsearch host cannot stall the PHP-FPM worker during terminate();
    added `elastic` and `circuit_breaker` config sections;
    added `circuit_breaker.log_channel` to route the circuit-opened warning to a dedicated log channel;
    added PHPUnit test suite (tests/Unit/CircuitBreakerTest.php) with 6 cases covering short-circuit, opening, configurable channel, disabled breaker, ignored environments and cache-store failures;

2024-11-07 - 1.0

    first version;
