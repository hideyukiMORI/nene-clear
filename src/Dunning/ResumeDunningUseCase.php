<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class ResumeDunningUseCase
{
    public function __construct(
        private DunningPauseRepositoryInterface $pauses,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(int $organizationId, int $invoiceId, int $actorUserId): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $this->pauses->resumeByInvoice($organizationId, $invoiceId, $actorUserId, $now);

        $this->auditEvents->record(new AuditEvent(
            organizationId: $organizationId,
            eventType: 'dunning_resumed',
            actorUserId: $actorUserId,
            occurredAt: $now,
            payload: [
                'invoice_id' => $invoiceId,
                'before' => ['is_paused' => true],
                'after' => ['is_paused' => false],
            ],
        ));
    }
}
