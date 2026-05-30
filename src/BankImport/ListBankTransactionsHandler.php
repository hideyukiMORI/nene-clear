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
        $q = $request->getQueryParams();

        $statusParam = $q['status'] ?? null;
        $status = is_string($statusParam) ? BankTransactionStatus::tryFrom($statusParam) : null;

        $valueDateFrom = isset($q['value_date_from']) && is_string($q['value_date_from']) ? $q['value_date_from'] : null;
        $valueDateTo = isset($q['value_date_to']) && is_string($q['value_date_to']) ? $q['value_date_to'] : null;

        $amountMinParam = $q['amount_min_cents'] ?? null;
        $amountMinCents = is_numeric($amountMinParam) ? (int) $amountMinParam : null;

        $amountMaxParam = $q['amount_max_cents'] ?? null;
        $amountMaxCents = is_numeric($amountMaxParam) ? (int) $amountMaxParam : null;

        $counterparty = isset($q['counterparty']) && is_string($q['counterparty']) ? $q['counterparty'] : null;

        $filter = new BankTransactionFilter(
            status: $status,
            valueDateFrom: $valueDateFrom,
            valueDateTo: $valueDateTo,
            amountMinCents: $amountMinCents,
            amountMaxCents: $amountMaxCents,
            counterparty: $counterparty,
        );

        return $this->response->create([
            'items' => array_map(
                BankTransactionResponse::toArray(...),
                $this->transactions->findByOrganization($organizationId, $filter, $page->limit, $page->offset),
            ),
            'limit' => $page->limit,
            'offset' => $page->offset,
            'total' => $this->transactions->countByOrganization($organizationId, $filter),
        ]);
    }
}
