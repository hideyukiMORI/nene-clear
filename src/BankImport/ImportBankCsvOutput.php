<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

final readonly class ImportBankCsvOutput
{
    public function __construct(
        public int $bankImportBatchId,
        public string $fileHash,
        public int $rowCount,
    ) {
    }
}
