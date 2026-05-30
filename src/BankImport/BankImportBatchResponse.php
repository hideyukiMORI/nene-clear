<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

final readonly class BankImportBatchResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(BankImportBatch $batch): array
    {
        return [
            'bank_import_batch_id' => $batch->id,
            'organization_id' => $batch->organizationId,
            'bank_account_id' => $batch->bankAccountId,
            'file_hash' => $batch->fileHash,
            'source_filename' => $batch->sourceFilename,
            'row_count' => $batch->rowCount,
            'status' => $batch->status->value,
            'imported_at' => $batch->importedAt,
            'reversed_at' => $batch->reversedAt,
            'reversal_reason' => $batch->reversalReason,
        ];
    }
}
