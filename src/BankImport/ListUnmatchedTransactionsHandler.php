<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListUnmatchedTransactionsHandler
{
    public function __construct(
        private BankTransactionRepositoryInterface $transactions,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $this->organization->id();
        $page = PaginationQueryParser::parse($request, 50, 200);
        $filter = BankTransactionFilter::fromQueryParams($request->getQueryParams(), openForMatchingOnly: true);

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
