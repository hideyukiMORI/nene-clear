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
            'event_type' => $event->eventType,
            'actor_user_id' => $event->actorUserId,
            'occurred_at' => $event->occurredAt,
            'payload' => $event->payload,
        ];
    }
}
