<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ClearSettingsRouteRegistrar
{
    public function __construct(
        private GetClearSettingsHandler $getHandler,
        private UpdateClearSettingsHandler $updateHandler,
        private TestUpstreamConnectionHandler $testHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $getHandler = $this->getHandler;
        $updateHandler = $this->updateHandler;
        $testHandler = $this->testHandler;

        $router->get('/admin/clear-settings', static fn (ServerRequestInterface $r): ResponseInterface => $getHandler->handle($r));
        $router->put('/admin/clear-settings', static fn (ServerRequestInterface $r): ResponseInterface => $updateHandler->handle($r));
        $router->post('/admin/clear-settings/test-upstream', static fn (ServerRequestInterface $r): ResponseInterface => $testHandler->handle($r));
    }
}
