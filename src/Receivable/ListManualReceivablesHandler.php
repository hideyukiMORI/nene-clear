<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListManualReceivablesHandler
{
    public function __construct(
        private ListManualReceivablesUseCaseInterface $useCase,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $page = PaginationQueryParser::parse($request, 50, 200);
        $filter = ManualReceivableFilter::fromQueryParams((array) $request->getQueryParams());
        $output = $this->useCase->execute($this->organization->id(), $filter, $page->limit, $page->offset);

        return $this->response->create((new PaginationResponse(
            items: array_map(ManualReceivableResponse::toArray(...), $output->items),
            limit: $output->limit,
            offset: $output->offset,
            total: $output->total,
        ))->toArray());
    }
}
