<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

final readonly class ReverseBankImportBatchOutput
{
    public function __construct(
        public int $batchId,
        public int $rowsVoided,
    ) {
    }
}
