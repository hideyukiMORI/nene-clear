<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListUnmatchedTransactionsHandler
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

        $valueDateFrom = isset($q['value_date_from']) && is_string($q['value_date_from']) ? $q['value_date_from'] : null;
        $valueDateTo = isset($q['value_date_to']) && is_string($q['value_date_to']) ? $q['value_date_to'] : null;

        $amountMinParam = $q['amount_min_cents'] ?? null;
        $amountMinCents = is_numeric($amountMinParam) ? (int) $amountMinParam : null;

        $amountMaxParam = $q['amount_max_cents'] ?? null;
        $amountMaxCents = is_numeric($amountMaxParam) ? (int) $amountMaxParam : null;

        $counterparty = isset($q['counterparty']) && is_string($q['counterparty']) ? $q['counterparty'] : null;

        $sortBy = isset($q['sort_by']) && is_string($q['sort_by']) && $q['sort_by'] !== '' ? $q['sort_by'] : 'value_date';
        $sortDir = isset($q['sort_dir']) && is_string($q['sort_dir']) && $q['sort_dir'] !== '' ? $q['sort_dir'] : 'desc';

        $filter = new BankTransactionFilter(
            valueDateFrom: $valueDateFrom,
            valueDateTo: $valueDateTo,
            amountMinCents: $amountMinCents,
            amountMaxCents: $amountMaxCents,
            counterparty: $counterparty,
            sortColumn: $sortBy,
            sortDirection: $sortDir,
            openForMatchingOnly: true,
        );

        return $this->response->create((new PaginationResponse(
            items: array_map(
                BankTransactionResponse::toArray(...),
                $this->transactions->findUnmatchedByOrganization($organizationId, $filter, $page->limit, $page->offset),
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->transactions->countUnmatchedByOrganization($organizationId, $filter),
        ))->toArray());
    }
}
