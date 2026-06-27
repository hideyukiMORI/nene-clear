<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Self-service TOTP enrolment for the authenticated user. These routes are not
 * in {@see \NeneClear\Auth\AuthServiceProvider}'s public-path list, so they
 * require a session; they carry no capability — a user manages their own MFA.
 */
final readonly class MfaRouteRegistrar
{
    public function __construct(
        private SetupTotpHandler $setupHandler,
        private EnableTotpHandler $enableHandler,
        private GetTotpStatusHandler $statusHandler,
        private DisableTotpHandler $disableHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $setupHandler = $this->setupHandler;
        $enableHandler = $this->enableHandler;
        $statusHandler = $this->statusHandler;
        $disableHandler = $this->disableHandler;

        $router->post('/admin/auth/totp/setup', static fn (ServerRequestInterface $r): ResponseInterface => $setupHandler->handle($r));
        $router->post('/admin/auth/totp/enable', static fn (ServerRequestInterface $r): ResponseInterface => $enableHandler->handle($r));
        $router->get('/admin/auth/totp', static fn (ServerRequestInterface $r): ResponseInterface => $statusHandler->handle($r));
        $router->delete('/admin/auth/totp', static fn (ServerRequestInterface $r): ResponseInterface => $disableHandler->handle($r));
    }
}
