<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditRecorderInterface;

final readonly class ResumeDunningUseCase
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

    public function execute(int $organizationId, int $invoiceId, int $actorUserId): void
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        // The resume update and its audit record commit (or roll back) together (Issue #122).
        $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $ex) use ($organizationId, $invoiceId, $actorUserId, $now): void {
                $pauses = ($this->pauses)($ex);
                $auditRecorder = ($this->auditRecorder)($ex);

                $pauses->resumeByInvoice($organizationId, $invoiceId, $actorUserId, $now);

                $auditRecorder->record(
                    $organizationId,
                    $actorUserId,
                    $now,
                    'dunning_resumed',
                    'invoice',
                    $invoiceId,
                    [
                        'invoice_id' => $invoiceId,
                        'before' => ['is_paused' => true],
                        'after' => ['is_paused' => false],
                    ],
                );
            },
        );
    }
}
