<?php

declare(strict_types=1);

namespace NeneClear\Export;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ExportRouteRegistrar
{
    public function __construct(
        private ExportReconciliationsCsvHandler $reconciliationsHandler,
        private ExportClientCreditsCsvHandler $clientCreditsHandler,
        private ExportBankTransactionsCsvHandler $bankTransactionsHandler,
        private ExportDunningNoticesCsvHandler $dunningNoticesHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $reconciliations = $this->reconciliationsHandler;
        $clientCredits = $this->clientCreditsHandler;
        $bankTransactions = $this->bankTransactionsHandler;
        $dunningNotices = $this->dunningNoticesHandler;

        $router->get('/admin/export/reconciliations', static fn (ServerRequestInterface $r): ResponseInterface => $reconciliations->handle($r));
        $router->get('/admin/export/client-credits', static fn (ServerRequestInterface $r): ResponseInterface => $clientCredits->handle($r));
        $router->get('/admin/export/bank-transactions', static fn (ServerRequestInterface $r): ResponseInterface => $bankTransactions->handle($r));
        $router->get('/admin/export/dunning-notices', static fn (ServerRequestInterface $r): ResponseInterface => $dunningNotices->handle($r));
    }
}
