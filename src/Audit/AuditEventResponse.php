<?php

declare(strict_types=1);

namespace NeneClear\Audit;

final readonly class AuditEventResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(AuditEvent $event): array
    {
        return [
            'audit_event_id' => $event->id,
            'organization_id' => $event->organizationId,
            'action' => $event->action,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'actor_id' => $event->actorId,
            'occurred_at' => $event->occurredAt,
            'before' => $event->before,
            'after' => $event->after,
            'metadata' => $event->metadata,
        ];
    }
}
