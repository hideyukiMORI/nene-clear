<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

/**
 * Provenance of one CSV import (電子帳簿保存法 / ADR 0012). `file_hash` is the
 * SHA-256 of the imported file and drives duplicate-import detection.
 */
final readonly class BankImportBatch
{
    public function __construct(
        public int $organizationId,
        public int $bankAccountId,
        public string $fileHash,
        public string $sourceFilename,
        public int $rowCount,
        public BankImportBatchStatus $status,
        public int $importedBy,
        public string $importedAt,
        public ?int $id = null,
    ) {
    }
}
