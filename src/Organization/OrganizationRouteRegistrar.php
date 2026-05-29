<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class OrganizationRouteRegistrar
{
    public function __construct(
        private ListOrganizationsHandler $listHandler,
        private CreateOrganizationHandler $createHandler,
        private GetOrganizationByIdHandler $getHandler,
        private DeleteOrganizationHandler $deleteHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $listHandler = $this->listHandler;
        $createHandler = $this->createHandler;
        $getHandler = $this->getHandler;
        $deleteHandler = $this->deleteHandler;

        $router->get('/admin/organizations', static fn (ServerRequestInterface $r): ResponseInterface => $listHandler->handle($r));
        $router->post('/admin/organizations', static fn (ServerRequestInterface $r): ResponseInterface => $createHandler->handle($r));
        $router->get('/admin/organizations/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $getHandler->handle($r));
        $router->delete('/admin/organizations/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $deleteHandler->handle($r));
    }
}
