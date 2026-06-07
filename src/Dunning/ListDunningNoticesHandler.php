<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListDunningNoticesHandler
{
    public function __construct(
        private DunningNoticeRepositoryInterface $notices,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $page = PaginationQueryParser::parse($request, 50, 200);
        $filter = DunningNoticeFilter::fromQueryParams($request->getQueryParams());

        return $this->response->create((new PaginationResponse(
            items: array_map(
                DunningNoticeResponse::toArray(...),
                $this->notices->findByOrganization($organizationId, $filter, $page->limit, $page->offset),
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->notices->countByOrganization($organizationId, $filter),
        ))->toArray());
    }
}
