<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Closure;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Database\DatabaseTransactionManagerInterface;
use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class ImportBankCsvUseCase implements ImportBankCsvUseCaseInterface
{
    /**
     * @param Closure(DatabaseQueryExecutorInterface): BankAccountRepositoryInterface $bankAccounts
     * @param Closure(DatabaseQueryExecutorInterface): BankImportBatchRepositoryInterface $batches
     * @param Closure(DatabaseQueryExecutorInterface): BankTransactionRepositoryInterface $transactions
     * @param Closure(DatabaseQueryExecutorInterface): AuditEventRepositoryInterface $auditEvents
     */
    public function __construct(
        private DatabaseTransactionManagerInterface $transactionManager,
        private Closure $bankAccounts,
        private Closure $batches,
        private Closure $transactions,
        private Closure $auditEvents,
        private BankCsvParser $parser,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ImportBankCsvInput $input): ImportBankCsvOutput
    {
        // The batch row, every transaction line, and the audit record commit (or
        // roll back) as one unit, so a partially-imported batch or a batch without
        // its audit event can never be persisted (Issue #122).
        return $this->transactionManager->transactional(
            function (DatabaseQueryExecutorInterface $tx) use ($input): ImportBankCsvOutput {
                $bankAccounts = ($this->bankAccounts)($tx);
                $batches = ($this->batches)($tx);
                $transactions = ($this->transactions)($tx);
                $auditEvents = ($this->auditEvents)($tx);

                $account = $bankAccounts->findById($input->bankAccountId);

                if ($account === null || $account->organizationId !== $input->organizationId) {
                    throw new BankAccountNotFoundException($input->bankAccountId);
                }

                $fileHash = hash('sha256', $input->contents);

                if ($batches->existsByFileHash($input->organizationId, $fileHash)) {
                    throw new DuplicateBankImportException($fileHash);
                }

                $lines = $this->parser->parse($input->contents, $account);
                $now = $this->clock->now()->format('Y-m-d H:i:s');

                $batchId = $batches->save(new BankImportBatch(
                    organizationId: $input->organizationId,
                    bankAccountId: $input->bankAccountId,
                    fileHash: $fileHash,
                    sourceFilename: $input->sourceFilename,
                    rowCount: count($lines),
                    status: BankImportBatchStatus::Imported,
                    importedBy: $input->actorUserId,
                    importedAt: $now,
                ));

                foreach ($lines as $line) {
                    $transactions->save(new BankTransaction(
                        organizationId: $input->organizationId,
                        bankImportBatchId: $batchId,
                        bankAccountId: $input->bankAccountId,
                        valueDate: $line->valueDate,
                        amountCents: $line->amountCents,
                        counterpartyText: $line->counterpartyText,
                        lineKey: $line->lineKey,
                        status: BankTransactionStatus::Unmatched,
                    ));
                }

                $auditEvents->record(new AuditEvent(
                    organizationId: $input->organizationId,
                    eventType: 'bank_import',
                    actorUserId: $input->actorUserId,
                    occurredAt: $now,
                    payload: [
                        'after' => [
                            'bank_import_batch_id' => $batchId,
                            'file_hash' => $fileHash,
                            'row_count' => count($lines),
                            'source_filename' => $input->sourceFilename,
                        ],
                    ],
                ));

                return new ImportBankCsvOutput(
                    bankImportBatchId: $batchId,
                    fileHash: $fileHash,
                    rowCount: count($lines),
                );
            },
        );
    }
}
