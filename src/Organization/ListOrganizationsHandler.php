<?php

declare(strict_types=1);

namespace NeneClear\Organization;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Pagination defaults align with docs/openapi/openapi.yaml (default 50, max 200).

final readonly class ListOrganizationsHandler
{
    public function __construct(
        private ListOrganizationsUseCaseInterface $useCase,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $page = PaginationQueryParser::parse($request, 50, 200);
        $output = $this->useCase->execute($page->limit, $page->offset);

        return $this->response->create((new PaginationResponse(
            items: array_map(
                static fn (Organization $o): array => [
                    'organization_id' => $o->id,
                    'slug' => $o->slug,
                    'name' => $o->name,
                ],
                $output->items,
            ),
            limit: $output->limit,
            offset: $output->offset,
            total: $output->total,
        ))->toArray());
    }
}
