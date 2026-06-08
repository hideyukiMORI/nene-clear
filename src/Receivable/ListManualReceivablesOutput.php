<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

final readonly class ListManualReceivablesOutput
{
    /**
     * @param list<ManualReceivable> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
    ) {
    }
}
