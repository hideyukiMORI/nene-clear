<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class DunningRouteRegistrar
{
    public function __construct(
        private SendDunningHandler $sendHandler,
        private ListDunningNoticesHandler $listHandler,
        private GetDunningNoticeByIdHandler $getByIdHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $sendHandler = $this->sendHandler;
        $listHandler = $this->listHandler;
        $getByIdHandler = $this->getByIdHandler;

        $router->post('/admin/dunning-notices', static fn (ServerRequestInterface $r): ResponseInterface => $sendHandler->handle($r));
        $router->get('/admin/dunning-notices', static fn (ServerRequestInterface $r): ResponseInterface => $listHandler->handle($r));
        $router->get('/admin/dunning-notices/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $getByIdHandler->handle($r));
    }
}
