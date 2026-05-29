<?php

declare(strict_types=1);

namespace NeneClear\User;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UserRouteRegistrar
{
    public function __construct(
        private ListUsersHandler $listHandler,
        private CreateUserHandler $createHandler,
        private GetUserByIdHandler $getHandler,
        private UpdateUserHandler $updateHandler,
        private DeleteUserHandler $deleteHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $listHandler = $this->listHandler;
        $createHandler = $this->createHandler;
        $getHandler = $this->getHandler;
        $updateHandler = $this->updateHandler;
        $deleteHandler = $this->deleteHandler;

        $router->get('/admin/users', static fn (ServerRequestInterface $r): ResponseInterface => $listHandler->handle($r));
        $router->post('/admin/users', static fn (ServerRequestInterface $r): ResponseInterface => $createHandler->handle($r));
        $router->get('/admin/users/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $getHandler->handle($r));
        $router->put('/admin/users/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $updateHandler->handle($r));
        $router->delete('/admin/users/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $deleteHandler->handle($r));
    }
}
