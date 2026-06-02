<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class AuditRouteRegistrar
{
    public function __construct(
        private ListAuditEventsHandler $listHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $listHandler = $this->listHandler;

        $router->get('/admin/audit-events', static fn (ServerRequestInterface $r): ResponseInterface => $listHandler->handle($r));
    }
}
