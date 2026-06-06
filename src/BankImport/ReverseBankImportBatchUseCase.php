<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class ReverseBankImportBatchUseCase implements ReverseBankImportBatchUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): BankImportBatchRepositoryInterface $batches
     * @param Closure(DatabaseQueryExecutorInterface): BankTransactionRepositoryInterface $transactions
     * @param Closure(DatabaseQueryExecutorInterface): AuditEventRepositoryInterface $auditEvents
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $batches,
        private Closure $transactions,
        private Closure $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ReverseBankImportBatchInput $input): ReverseBankImportBatchOutput
    {
        // Voiding the lines, marking the batch reversed, and recording the audit
        // event commit (or roll back) together (Issue #122).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($input): ReverseBankImportBatchOutput {
                $batches = ($this->batches)($tx);
                $transactions = ($this->transactions)($tx);
                $auditEvents = ($this->auditEvents)($tx);

                $batch = $batches->findById($input->batchId);

                if ($batch === null || $batch->organizationId !== $input->organizationId) {
                    throw new BankImportBatchNotFoundException($input->batchId);
                }

                if ($batch->status !== BankImportBatchStatus::Imported) {
                    throw new BankImportBatchAlreadyReversedException($input->batchId);
                }

                $lines = $transactions->findByBatch($input->batchId);

                foreach ($lines as $line) {
                    if ($line->status === BankTransactionStatus::Matched || $line->status === BankTransactionStatus::PartiallyMatched) {
                        throw new BankImportBatchHasMatchedLinesException($input->batchId);
                    }
                }

                $unmatchedCount = count(array_filter(
                    $lines,
                    static fn (BankTransaction $t): bool => $t->status === BankTransactionStatus::Unmatched,
                ));

                $now = $this->clock->now()->format('Y-m-d H:i:s');

                $transactions->voidByBatchId($input->batchId);
                $batches->reverseById($input->batchId, $now, $input->reversalReason);

                $auditEvents->record(new AuditEvent(
                    organizationId: $input->organizationId,
                    eventType: 'bank_import_batch_reversed',
                    actorUserId: $input->actorUserId,
                    occurredAt: $now,
                    payload: [
                        'bank_import_batch_id' => $input->batchId,
                        'before' => [
                            'status' => 'imported',
                            'row_count' => count($lines),
                        ],
                        'after' => [
                            'status' => 'reversed',
                            'reversal_reason' => $input->reversalReason,
                            'rows_voided' => $unmatchedCount,
                        ],
                    ],
                ));

                return new ReverseBankImportBatchOutput(
                    batchId: $input->batchId,
                    rowsVoided: $unmatchedCount,
                );
            },
        );
    }
}
