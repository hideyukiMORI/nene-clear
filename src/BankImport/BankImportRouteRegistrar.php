<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class BankImportRouteRegistrar
{
    public function __construct(
        private ImportBankCsvHandler $importHandler,
        private ListBankImportBatchesHandler $listBatchesHandler,
        private ListBankTransactionsHandler $listTransactionsHandler,
        private ListUnmatchedTransactionsHandler $listUnmatchedHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $importHandler = $this->importHandler;
        $listBatchesHandler = $this->listBatchesHandler;
        $listTransactionsHandler = $this->listTransactionsHandler;
        $listUnmatchedHandler = $this->listUnmatchedHandler;

        $router->post('/admin/bank-import-batches', static fn (ServerRequestInterface $r): ResponseInterface => $importHandler->handle($r));
        $router->get('/admin/bank-import-batches', static fn (ServerRequestInterface $r): ResponseInterface => $listBatchesHandler->handle($r));
        // Unmatched is registered before the generic list so the more specific path wins.
        $router->get('/admin/bank-transactions/unmatched', static fn (ServerRequestInterface $r): ResponseInterface => $listUnmatchedHandler->handle($r));
        $router->get('/admin/bank-transactions', static fn (ServerRequestInterface $r): ResponseInterface => $listTransactionsHandler->handle($r));
    }
}
