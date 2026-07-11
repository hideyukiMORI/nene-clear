<?php

declare(strict_types=1);

namespace NeneClear\Audit;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationQueryParser;
use Nene2\Http\PaginationResponse;
use NeneClear\Tenancy\CurrentOrganization;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListAuditEventsHandler
{
    public function __construct(
        private AuditReadRepositoryInterface $auditEvents,
        private JsonResponseFactory $response,
        private CurrentOrganization $organization,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = $this->organization->id();
        $page = PaginationQueryParser::parse($request, 50, 200);

        $q = $request->getQueryParams();
        $str = static fn (string $k): ?string => isset($q[$k]) && is_string($q[$k]) && $q[$k] !== '' ? $q[$k] : null;
        $int = static fn (string $k): ?int => isset($q[$k]) && is_numeric($q[$k]) ? (int) $q[$k] : null;

        $filter = new AuditEventFilter(
            action: $str('action'),
            entityType: $str('entity_type'),
            entityId: $int('entity_id'),
            actorId: $int('actor_id'),
            occurredFrom: $str('occurred_from'),
            occurredTo: $str('occurred_to'),
            sortColumn: $str('sort_by') ?? 'occurred_at',
            sortDirection: $str('sort_dir') ?? 'desc',
        );

        return $this->response->create((new PaginationResponse(
            items: array_map(
                static fn (AuditEvent $e): array => AuditEventResponse::toArray($e),
                $this->auditEvents->findByOrganization($organizationId, $filter, $page->limit, $page->offset),
            ),
            limit: $page->limit,
            offset: $page->offset,
            total: $this->auditEvents->countByOrganization($organizationId, $filter),
        ))->toArray());
    }
}
