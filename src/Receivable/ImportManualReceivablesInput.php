<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

final readonly class ImportManualReceivablesInput
{
    public function __construct(
        public int $organizationId,
        public string $contents,
        public int $actorUserId,
    ) {
    }
}
