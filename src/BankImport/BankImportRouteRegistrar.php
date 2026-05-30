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
        private ReverseBankImportBatchHandler $reverseBatchHandler,
        private ListBankTransactionsHandler $listTransactionsHandler,
        private ListUnmatchedTransactionsHandler $listUnmatchedHandler,
        private GetBankTransactionByIdHandler $getTransactionByIdHandler,
    ) {
    }

    public function __invoke(Router $router): void
    {
        $importHandler = $this->importHandler;
        $listBatchesHandler = $this->listBatchesHandler;
        $reverseBatchHandler = $this->reverseBatchHandler;
        $listTransactionsHandler = $this->listTransactionsHandler;
        $listUnmatchedHandler = $this->listUnmatchedHandler;
        $getTransactionByIdHandler = $this->getTransactionByIdHandler;

        $router->post('/admin/bank-import-batches', static fn (ServerRequestInterface $r): ResponseInterface => $importHandler->handle($r));
        $router->get('/admin/bank-import-batches', static fn (ServerRequestInterface $r): ResponseInterface => $listBatchesHandler->handle($r));
        $router->post('/admin/bank-import-batches/{id}/reverse', static fn (ServerRequestInterface $r): ResponseInterface => $reverseBatchHandler->handle($r));
        // Unmatched is registered before the generic list so the more specific path wins.
        $router->get('/admin/bank-transactions/unmatched', static fn (ServerRequestInterface $r): ResponseInterface => $listUnmatchedHandler->handle($r));
        $router->get('/admin/bank-transactions', static fn (ServerRequestInterface $r): ResponseInterface => $listTransactionsHandler->handle($r));
        $router->get('/admin/bank-transactions/{id}', static fn (ServerRequestInterface $r): ResponseInterface => $getTransactionByIdHandler->handle($r));
    }
}
