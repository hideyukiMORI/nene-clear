<?php

declare(strict_types=1);

namespace NeneClear\Audit;

final readonly class AuditRecorder implements AuditRecorderInterface
{
    public function __construct(
        private AuditEventRepositoryInterface $repository,
    ) {
    }

    public function record(
        int $organizationId,
        int $actorUserId,
        string $occurredAt,
        string $eventType,
        string $entityType,
        ?int $entityId,
        array $payload,
    ): int {
        return $this->repository->record(new AuditEvent(
            organizationId: $organizationId,
            eventType: $eventType,
            entityType: $entityType,
            entityId: $entityId,
            actorUserId: $actorUserId,
            occurredAt: $occurredAt,
            payload: $payload,
        ));
    }
}
