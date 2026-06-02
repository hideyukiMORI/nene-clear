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

        $eventTypeParam = $request->getQueryParams()['event_type'] ?? null;
        $eventType = is_string($eventTypeParam) && $eventTypeParam !== '' ? $eventTypeParam : null;

        $items = $this->auditEvents->findByOrganization($organizationId, $eventType, $page->limit, $page->offset);

        return $this->response->create((new PaginationResponse(
            items: array_map(
                static fn (AuditEvent $e): array => AuditEventResponse::toArray($e),
                $items,
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->auditEvents->countByOrganization($organizationId, $eventType),
        ))->toArray());
    }
}
