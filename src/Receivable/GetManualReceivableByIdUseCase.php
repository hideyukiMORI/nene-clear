<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

final readonly class GetManualReceivableByIdUseCase implements GetManualReceivableByIdUseCaseInterface
{
    public function __construct(
        private ManualReceivableRepositoryInterface $receivables,
    ) {
    }

    public function execute(int $id, int $callerOrganizationId): ManualReceivable
    {
        $receivable = $this->receivables->findById($id);

        if ($receivable === null || $receivable->organizationId !== $callerOrganizationId) {
            throw new ManualReceivableNotFoundException($id);
        }

        return $receivable;
    }
}
