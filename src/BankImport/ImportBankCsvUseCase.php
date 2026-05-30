<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

use Nene2\Http\ClockInterface;
use NeneClear\Audit\AuditEvent;
use NeneClear\Audit\AuditEventRepositoryInterface;

final readonly class ImportBankCsvUseCase implements ImportBankCsvUseCaseInterface
{
    public function __construct(
        private BankAccountRepositoryInterface $bankAccounts,
        private BankImportBatchRepositoryInterface $batches,
        private BankTransactionRepositoryInterface $transactions,
        private AuditEventRepositoryInterface $auditEvents,
        private BankCsvParser $parser,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ImportBankCsvInput $input): ImportBankCsvOutput
    {
        $account = $this->bankAccounts->findById($input->bankAccountId);

        if ($account === null || $account->organizationId !== $input->organizationId) {
            throw new BankAccountNotFoundException($input->bankAccountId);
        }

        $fileHash = hash('sha256', $input->contents);

        if ($this->batches->existsByFileHash($input->organizationId, $fileHash)) {
            throw new DuplicateBankImportException($fileHash);
        }

        $lines = $this->parser->parse($input->contents, $account);
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $batchId = $this->batches->save(new BankImportBatch(
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
            $this->transactions->save(new BankTransaction(
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

        $this->auditEvents->record(new AuditEvent(
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
    }
}
