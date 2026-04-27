# Changelog

2026-04-27 - 2.0.0

    SECURITY: fixed `OfficegestApiLoggerServiceProvider::register()` reading `masked_fields` from the wrong config key — `config('officegest-api-logger.masked_fields')` instead of `config('officegest-api-logger-config.masked_fields')`. Since the package was first published the lookup returned `null`, the `is_array()` guard fell through to `[]`, and the `FieldMasker` was constructed with an empty fields list. As a result every entry in the published `masked_fields` config (`password`, `pwd`, `secret`, `password_confirmation`, `cc`, `card_number`, `ccv`, `ssn`, `credit_score`, `api_key`) was forwarded to Elasticsearch in plaintext — only the hardcoded `authorization` and `x-api-key` request headers in `FieldMasker::isSensitiveHeader()` were ever masked. Login bodies, payment payloads and api-key headers were therefore stored in clear in every backing index. Operators of upgraded hosts SHOULD audit and consider purging existing Elasticsearch indices that contain unmasked credentials, or wait out their retention window before relying on the masking guarantee. Added `DataFactoryTest::masks_sensitive_request_body_fields_from_config` pinning the corrected behaviour;
    BREAKING: removed `data.request.raw` from the Elasticsearch payload and removed `$raw` from the `OfficegestApiLogger\DataObjects\Request` constructor. Since 1.0 the field had been a verbatim copy of `data.request.body` — both were populated by `$this->masker->mask($request->all())` called twice in `DataFactory::make()` — so every log doc was carrying the parsed request body twice and the masker was being run twice on the same data for zero added value. Consumers reading `data.request.raw` from Elasticsearch must switch to `data.request.body`. Existing backing indices keep the old field until ILM/data-stream retention rotates them out;
    updated DataFactoryTest with a regression test asserting `__toArray()` no longer emits a `raw` key and that body masking still runs;

2026-04-24 - 1.6.1

    fixed the Elasticsearch HTTP client options passing `connect_timeout`, a Guzzle-only option that Symfony HttpClient rejects with "Unsupported option" and that depending on which client `php-http/discovery` picked would silently open the circuit breaker on every log attempt. Dropped the key from `setHttpClientOptions()`; only `timeout` is now passed, which both Guzzle and Symfony HttpClient accept. The bug had been latent since 1.4.0 and only surfaced in environments where Symfony HttpClient was discovered ahead of Guzzle. The `elastic.connect_timeout` config key is retained (silently ignored) to avoid breaking hosts that have it set in their `.env`;

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
