<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

final readonly class ImportBankCsvInput
{
    public function __construct(
        public int $organizationId,
        public int $bankAccountId,
        public string $sourceFilename,
        public string $contents,
        public int $actorUserId,
    ) {
    }
}
