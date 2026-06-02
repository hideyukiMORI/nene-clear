<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\JsonResponseFactory;
use Nene2\Http\PaginationResponse;
use NeneClear\Auth\AuthContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ListDunningPausesHandler
{
    public function __construct(
        private DunningPauseRepositoryInterface $pauses,
        private JsonResponseFactory $response,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $organizationId = AuthContext::organizationId($request) ?? 0;
        $q = (array) $request->getQueryParams();
        $activeOnly = ($q['active_only'] ?? null) === 'true';
        $limit = is_numeric($q['limit'] ?? null) ? (int) $q['limit'] : 50;
        $offset = is_numeric($q['offset'] ?? null) ? (int) $q['offset'] : 0;

        $items = $this->pauses->findByOrganization($organizationId, $activeOnly, $limit, $offset);
        $total = $this->pauses->countByOrganization($organizationId, $activeOnly);

        return $this->response->create((new PaginationResponse(
            items: array_map(static fn (DunningPause $p): array => [
                'dunning_pause_id' => $p->id,
                'invoice_id' => $p->invoiceId,
                'paused_by' => $p->pausedBy,
                'paused_at' => $p->pausedAt,
                'paused_reason' => $p->pausedReason,
                'unpaused_by' => $p->unpausedBy,
                'unpaused_at' => $p->unpausedAt,
                'is_active' => $p->isActive(),
            ], $items),
            limit: $limit,
            offset: $offset,
            total: $total,
        ))->toArray());
    }
}
