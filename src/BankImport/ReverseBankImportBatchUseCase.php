<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class ReverseBankImportBatchUseCase implements ReverseBankImportBatchUseCaseInterface
{
    public function __construct(
        private BankImportBatchRepositoryInterface $batches,
        private BankTransactionRepositoryInterface $transactions,
        private AuditEventRepositoryInterface $auditEvents,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ReverseBankImportBatchInput $input): ReverseBankImportBatchOutput
    {
        $batch = $this->batches->findById($input->batchId);

        if ($batch === null || $batch->organizationId !== $input->organizationId) {
            throw new BankImportBatchNotFoundException($input->batchId);
        }

        if ($batch->status !== BankImportBatchStatus::Imported) {
            throw new BankImportBatchAlreadyReversedException($input->batchId);
        }

        $lines = $this->transactions->findByBatch($input->batchId);

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

        $this->transactions->voidByBatchId($input->batchId);
        $this->batches->reverseById($input->batchId, $now, $input->reversalReason);

        $this->auditEvents->record(new AuditEvent(
            organizationId: $input->organizationId,
            eventType: 'bank_import_batch_reversed',
            actorUserId: $input->actorUserId,
            occurredAt: $now,
            payload: [
                'bank_import_batch_id' => $input->batchId,
                'reversal_reason' => $input->reversalReason,
                'rows_voided' => $unmatchedCount,
            ],
        ));

        return new ReverseBankImportBatchOutput(
            batchId: $input->batchId,
            rowsVoided: $unmatchedCount,
        );
    }
}
