<?php

declare(strict_types=1);

namespace NeneClear\Http;

use Nene2\Auth\BearerTokenMiddleware;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Error\ProblemDetailsResponseFactory;
use Nene2\Http\JsonResponseFactory;
use Nene2\Http\RuntimeApplicationFactory;
use NeneClear\Auth\AuthRouteRegistrar;
use NeneClear\Auth\GetCurrentUserHandler;
use NeneClear\Auth\InvalidCredentialsExceptionHandler;
use NeneClear\Auth\JwtTokenService;
use NeneClear\Auth\LoginHandler;
use NeneClear\Auth\LoginUseCase;
use NeneClear\User\PdoUserRepository;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Builds the NeNe Clear HTTP application on top of the NENE2 runtime.
 *
 * The framework baseline pipeline (error handling, request id, security headers,
 * CORS, request size limit) and the built-in `/health` route are always present.
 *
 * Authenticated routes (`/admin/auth/*`, future `/admin/*`) are wired only when
 * **both** a database query executor and a JWT secret are provided. Without them
 * the app still serves the public/health surface, so `/health` never depends on a
 * database. See docs/development/nene2-compliance.md.
 */
final class ApplicationFactory
{
    /**
     * Public paths that never require a bearer token.
     *
     * @var list<string>
     */
    private const array PUBLIC_PATHS = ['/', '/health', '/machine/health', '/admin/auth/login'];

    /**
     * @param list<string> $allowedOrigins CORS allowlist; empty disables CORS (set explicitly in production).
     */
    public static function create(
        bool $debug = false,
        array $allowedOrigins = [],
        ?DatabaseQueryExecutorInterface $query = null,
        ?string $jwtSecret = null,
    ): RequestHandlerInterface {
        $psr17 = new Psr17Factory();
        $json = new JsonResponseFactory($psr17, $psr17);
        $problemDetails = new ProblemDetailsResponseFactory($psr17, $psr17);

        $routeRegistrars = [];
        $domainExceptionHandlers = [];
        $authMiddleware = null;

        if ($query !== null && $jwtSecret !== null && $jwtSecret !== '') {
            $jwt = new JwtTokenService($jwtSecret);
            $users = new PdoUserRepository($query);

            $routeRegistrars[] = new AuthRouteRegistrar(
                new LoginHandler(new LoginUseCase($users, $jwt), $json),
                new GetCurrentUserHandler($users, $json, $problemDetails),
            );
            $domainExceptionHandlers[] = new InvalidCredentialsExceptionHandler($problemDetails);
            $authMiddleware = [
                new BearerTokenMiddleware($problemDetails, $jwt, excludedPaths: self::PUBLIC_PATHS),
            ];
        }

        return (new RuntimeApplicationFactory(
            responseFactory: $psr17,
            streamFactory: $psr17,
            domainExceptionHandlers: $domainExceptionHandlers,
            routeRegistrars: $routeRegistrars,
            authMiddleware: $authMiddleware,
            debug: $debug,
            allowedOrigins: $allowedOrigins,
        ))->create();
    }
}
