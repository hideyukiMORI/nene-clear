<?php

declare(strict_types=1);

namespace NeneClear\Auth;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AuthRouteRegistrar
{
    public function __construct(
        private LoginHandler $loginHandler,
        private GetCurrentUserHandler $currentUserHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $loginHandler = $this->loginHandler;
        $currentUserHandler = $this->currentUserHandler;

        $router->post('/admin/auth/login', static fn (ServerRequestInterface $r): ResponseInterface => $loginHandler->handle($r));
        $router->get('/admin/auth/me', static fn (ServerRequestInterface $r): ResponseInterface => $currentUserHandler->handle($r));
    }
}
