<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ConfirmMatchInput
{
    /**
     * @param list<AllocationInput> $allocations
     */
    public function __construct(
        public int $organizationId,
        public int $bankTransactionId,
        public array $allocations,
        public int $actorUserId,
        public ?string $reasonCode = null,
    ) {
    }
}
