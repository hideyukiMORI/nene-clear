<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListAuditEventsHandler
{
    public function __construct(
        private AuditEventRepositoryInterface $auditEvents,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $page = PaginationQueryParser::parse($request, 50, 200);

        $query = $request->getQueryParams();

        $eventTypeParam = $query['event_type'] ?? null;
        $eventType = is_string($eventTypeParam) && $eventTypeParam !== '' ? $eventTypeParam : null;

        $entityTypeParam = $query['entity_type'] ?? null;
        $entityType = is_string($entityTypeParam) && $entityTypeParam !== '' ? $entityTypeParam : null;

        $entityIdParam = $query['entity_id'] ?? null;
        $entityId = is_numeric($entityIdParam) ? (int) $entityIdParam : null;

        $items = $this->auditEvents->findByOrganization($organizationId, $eventType, $entityType, $entityId, $page->limit, $page->offset);

        return $this->response->create((new PaginationResponse(
            items: array_map(
                static fn (AuditEvent $e): array => AuditEventResponse::toArray($e),
                $items,
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->auditEvents->countByOrganization($organizationId, $eventType, $entityType, $entityId),
        ))->toArray());
    }
}
