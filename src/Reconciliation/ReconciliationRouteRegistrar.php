<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ReconciliationRouteRegistrar
{
    public function __construct(
        private ProposeMatchHandler $proposeHandler,
        private ConfirmMatchHandler $confirmHandler,
        private ListReconciliationsHandler $listHandler,
        private GetReconciliationByIdHandler $getByIdHandler,
        private ReverseReconciliationHandler $reverseHandler,
        private ListClientCreditsHandler $listCreditsHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $proposeHandler = $this->proposeHandler;
        $confirmHandler = $this->confirmHandler;
        $listHandler = $this->listHandler;
        $getByIdHandler = $this->getByIdHandler;
        $reverseHandler = $this->reverseHandler;
        $listCreditsHandler = $this->listCreditsHandler;

        $router->post('/admin/reconciliations/propose', static fn (ServerRequestInterface $r): ResponseInterface => $proposeHandler->handle($r));
        $router->get('/admin/reconciliations', static fn (ServerRequestInterface $r): ResponseInterface => $listHandler->handle($r));
        $router->post('/admin/reconciliations', static fn (ServerRequestInterface $r): ResponseInterface => $confirmHandler->handle($r));
        $router->get('/admin/reconciliations/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $getByIdHandler->handle($r));
        $router->post('/admin/reconciliations/{id}/reverse', static fn (ServerRequestInterface $r): ResponseInterface => $reverseHandler->handle($r));
        $router->get('/admin/client-credits', static fn (ServerRequestInterface $r): ResponseInterface => $listCreditsHandler->handle($r));
    }
}
