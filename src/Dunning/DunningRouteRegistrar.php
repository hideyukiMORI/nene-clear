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
        private PreviewDunningNoticeHandler $previewHandler,
        private ListDunningNoticesHandler $listHandler,
        private GetDunningNoticeByIdHandler $getByIdHandler,
        private PauseDunningHandler $pauseHandler,
        private ResumeDunningHandler $resumeHandler,
        private ListDunningPausesHandler $listPausesHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $sendHandler = $this->sendHandler;
        $previewHandler = $this->previewHandler;
        $listHandler = $this->listHandler;
        $getByIdHandler = $this->getByIdHandler;
        $pauseHandler = $this->pauseHandler;
        $resumeHandler = $this->resumeHandler;
        $listPausesHandler = $this->listPausesHandler;

        $router->post('/admin/dunning-notices', static fn (ServerRequestInterface $r): ResponseInterface => $sendHandler->handle($r));
        $router->get('/admin/dunning-notices', static fn (ServerRequestInterface $r): ResponseInterface => $listHandler->handle($r));
        // Registered before /{id} so "preview" is not captured as an id.
        $router->get('/admin/dunning-notices/preview', static fn (ServerRequestInterface $r): ResponseInterface => $previewHandler->handle($r));
        $router->get('/admin/dunning-notices/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $getByIdHandler->handle($r));
        $router->post('/admin/dunning-pauses', static fn (ServerRequestInterface $r): ResponseInterface => $pauseHandler->handle($r));
        $router->post('/admin/dunning-pauses/{invoiceId}/resume', static fn (ServerRequestInterface $r): ResponseInterface => $resumeHandler->handle($r));
        $router->get('/admin/dunning-pauses', static fn (ServerRequestInterface $r): ResponseInterface => $listPausesHandler->handle($r));
    }
}
