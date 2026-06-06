<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;

final readonly class PauseDunningUseCase
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): DunningPauseRepositoryInterface $pauses
     * @param Closure(DatabaseQueryExecutorInterface): AuditRecorderInterface $auditRecorder
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $pauses,
        private Closure $auditRecorder,
        private ClockInterface $clock,
    ) {
    }

    public function execute(int $organizationId, int $invoiceId, int $actorUserId, string $reason): DunningPause
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // The pause insert and its audit record commit (or roll back) together (Issue #122).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $ex) use ($organizationId, $invoiceId, $actorUserId, $reason, $now): DunningPause {
                $pauses = ($this->pauses)($ex);
                $auditRecorder = ($this->auditRecorder)($ex);

                $existing = $pauses->findActiveByInvoice($organizationId, $invoiceId);
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

                $id = $pauses->save($pause);

                $auditRecorder->record(
                    $organizationId,
                    $actorUserId,
                    $now,
                    'dunning_paused',
                    'invoice',
                    $invoiceId,
                    [
                        'invoice_id' => $invoiceId,
                        'before' => ['is_paused' => false],
                        'after' => ['is_paused' => true, 'reason' => $reason],
                    ],
                );

                return new DunningPause(
                    organizationId: $pause->organizationId,
                    invoiceId: $pause->invoiceId,
                    pausedBy: $pause->pausedBy,
                    pausedAt: $pause->pausedAt,
                    pausedReason: $pause->pausedReason,
                    id: $id,
                );
            },
        );
    }
}
