<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class InvoiceUpstreamRouteRegistrar
{
    public function __construct(
        private ListUpstreamInvoicesHandler $listHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $listHandler = $this->listHandler;

        $router->get('/admin/upstream/invoices', static fn (ServerRequestInterface $r): ResponseInterface => $listHandler->handle($r));
    }
}
