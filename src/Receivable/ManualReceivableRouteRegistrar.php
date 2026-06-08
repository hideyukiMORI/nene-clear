<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ManualReceivableRouteRegistrar
{
    public function __construct(
        private ListManualReceivablesHandler $listHandler,
        private CreateManualReceivableHandler $createHandler,
        private GetManualReceivableHandler $getHandler,
        private UpdateManualReceivableHandler $updateHandler,
        private CancelManualReceivableHandler $cancelHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $listHandler = $this->listHandler;
        $createHandler = $this->createHandler;
        $getHandler = $this->getHandler;
        $updateHandler = $this->updateHandler;
        $cancelHandler = $this->cancelHandler;

        $router->get('/admin/manual-receivables', static fn (ServerRequestInterface $r): ResponseInterface => $listHandler->handle($r));
        $router->post('/admin/manual-receivables', static fn (ServerRequestInterface $r): ResponseInterface => $createHandler->handle($r));
        $router->get('/admin/manual-receivables/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $getHandler->handle($r));
        $router->put('/admin/manual-receivables/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $updateHandler->handle($r));
        $router->post('/admin/manual-receivables/{id}/cancel', static fn (ServerRequestInterface $r): ResponseInterface => $cancelHandler->handle($r));
    }
}
