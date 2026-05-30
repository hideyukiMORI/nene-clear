<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

final readonly class ReverseBankImportBatchInput
{
    public function __construct(
        public int $organizationId,
        public int $batchId,
        public int $actorUserId,
        public string $reversalReason,
    ) {
    }
}
