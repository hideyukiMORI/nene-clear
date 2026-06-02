<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class PauseDunningUseCase
{
    public function __construct(
        private DunningPauseRepositoryInterface $pauses,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(int $organizationId, int $invoiceId, int $actorUserId, string $reason): DunningPause
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $existing = $this->pauses->findActiveByInvoice($organizationId, $invoiceId);
        if ($existing !== null) {
            return $existing;
        }

        $pause = new DunningPause(
            organizationId: $organizationId,
            invoiceId: $invoiceId,
            pausedBy: $actorUserId,
            pausedAt: $now,
            pausedReason: $reason,
        );

        $id = $this->pauses->save($pause);

        $this->auditEvents->record(new AuditEvent(
            organizationId: $organizationId,
            eventType: 'dunning_paused',
            actorUserId: $actorUserId,
            occurredAt: $now,
            payload: [
                'invoice_id' => $invoiceId,
                'before' => ['is_paused' => false],
                'after' => ['is_paused' => true, 'reason' => $reason],
            ],
        ));

        return new DunningPause(
            organizationId: $pause->organizationId,
            invoiceId: $pause->invoiceId,
            pausedBy: $pause->pausedBy,
            pausedAt: $pause->pausedAt,
            pausedReason: $pause->pausedReason,
            id: $id,
        );
    }
}
