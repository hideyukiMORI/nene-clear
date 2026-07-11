<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListReconciliationsHandler
{
    public function __construct(
        private ReconciliationRepositoryInterface $reconciliations,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $this->organization->id();
        $page = PaginationQueryParser::parse($request, 50, 200);

        $filter = ReconciliationFilter::fromQueryParams($request->getQueryParams());

        return $this->response->create((new PaginationResponse(
            items: array_map(
                static fn (Reconciliation $r): array => ReconciliationResponse::toArray($r),
                $this->reconciliations->findByOrganization($organizationId, $filter, $page->limit, $page->offset),
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->reconciliations->countByOrganization($organizationId, $filter),
        ))->toArray());
    }
}
