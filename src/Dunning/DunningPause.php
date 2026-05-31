<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

final readonly class DunningPause
{
    public function __construct(
        public int $organizationId,
        public int $invoiceId,
        public int $pausedBy,
        public string $pausedAt,
        public string $pausedReason,
        public ?int $unpausedBy = null,
        public ?string $unpausedAt = null,
        public ?int $id = null,
    ) {
    }

    public function isActive(): bool
    {
        return $this->unpausedAt === null;
    }
}
