<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListBankTransactionsHandler
{
    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $page = PaginationQueryParser::parse($request, 50, 200);

        $statusParam = $request->getQueryParams()['status'] ?? null;
        $status = is_string($statusParam) ? BankTransactionStatus::tryFrom($statusParam) : null;

        return $this->response->create([
            'items' => array_map(
                BankTransactionResponse::toArray(...),
                $this->transactions->findByOrganization($organizationId, $status, $page->limit, $page->offset),
            ),
            'limit' => $page->limit,
            'offset' => $page->offset,
            'total' => $this->transactions->countByOrganization($organizationId, $status),
        ]);
    }
}
