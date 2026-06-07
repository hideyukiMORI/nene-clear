<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Public (unauthenticated) invitation endpoints — the invitee has no session
 * yet. Both paths are listed in AuthServiceProvider::PUBLIC_PATHS so the bearer
 * middleware lets them through; the token itself is the credential.
 */
final readonly class InvitationRouteRegistrar
{
    public function __construct(
        private GetInvitationHandler $getHandler,
        private AcceptInvitationHandler $acceptHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $getHandler = $this->getHandler;
        $acceptHandler = $this->acceptHandler;

        $router->get('/admin/auth/invitation', static fn (ServerRequestInterface $r): ResponseInterface => $getHandler->handle($r));
        $router->post('/admin/auth/invitation/accept', static fn (ServerRequestInterface $r): ResponseInterface => $acceptHandler->handle($r));
    }
}
