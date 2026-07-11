<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListClientCreditsHandler
{
    public function __construct(
        private ClientCreditRepositoryInterface $clientCredits,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $this->organization->id();
        $page = PaginationQueryParser::parse($request, 50, 200);
        $filter = ClientCreditFilter::fromQueryParams($request->getQueryParams());

        return $this->response->create((new PaginationResponse(
            items: array_map(
                ClientCreditResponse::toArray(...),
                $this->clientCredits->findByOrganization($organizationId, $filter, $page->limit, $page->offset),
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->clientCredits->countByOrganization($organizationId, $filter),
        ))->toArray());
    }
}
