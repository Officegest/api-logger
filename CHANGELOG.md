# Changelog

2026-04-20 - 1.4.0

    added circuit breaker around Elasticsearch writes; aggressive HTTP timeouts (connect 1s, total 1.5s) and setRetries(0) so an unreachable Elasticsearch host cannot stall the PHP-FPM worker during terminate();
    added `elastic` and `circuit_breaker` config sections;
    added `circuit_breaker.log_channel` to route the circuit-opened warning to a dedicated log channel;
    added PHPUnit test suite (tests/Unit/CircuitBreakerTest.php) with 6 cases covering short-circuit, opening, configurable channel, disabled breaker, ignored environments and cache-store failures;

2024-11-07 - 1.0

    first version;
