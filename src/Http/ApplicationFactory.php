<?php

declare(strict_types=1);

namespace NeneClear\Http;

use Nene2\Http\RuntimeApplicationFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Builds the NeNe Clear HTTP application on top of the NENE2 runtime.
 *
 * Wires the framework baseline pipeline (error handling, request id, security
 * headers, CORS, request size limit) and the built-in `/health` route via
 * {@see RuntimeApplicationFactory}. Application routes are added through
 * `$routeRegistrars` as Phase 1 endpoints land; see
 * docs/development/nene2-compliance.md and docs/openapi/openapi.yaml.
 */
final class ApplicationFactory
{
    /**
     * @param list<string> $allowedOrigins CORS allowlist; empty disables CORS (set explicitly in production).
     */
    public static function create(bool $debug = false, array $allowedOrigins = []): RequestHandlerInterface
    {
        $psr17 = new Psr17Factory();

        return (new RuntimeApplicationFactory(
            responseFactory: $psr17,
            streamFactory: $psr17,
            routeRegistrars: [],
            debug: $debug,
            allowedOrigins: $allowedOrigins,
        ))->create();
    }
}
