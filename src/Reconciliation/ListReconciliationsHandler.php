<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListReconciliationsHandler
{
    public function __construct(
        private ReconciliationRepositoryInterface $reconciliations,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $page = PaginationQueryParser::parse($request, 50, 200);

        $statusParam = $request->getQueryParams()['status'] ?? null;
        $status = is_string($statusParam) ? ReconciliationStatus::tryFrom($statusParam) : null;

        $items = $this->reconciliations->findByOrganization($organizationId, $status, $page->limit, $page->offset);

        return $this->response->create([
            'items' => array_map(
                static fn (Reconciliation $r): array => ReconciliationResponse::toArray($r),
                $items,
            ),
            'limit' => $page->limit,
            'offset' => $page->offset,
            'total' => $this->reconciliations->countByOrganization($organizationId, $status),
        ]);
    }
}
