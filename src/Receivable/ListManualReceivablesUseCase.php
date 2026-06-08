<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

final readonly class ListManualReceivablesUseCase implements ListManualReceivablesUseCaseInterface
{
    public function __construct(
        private ManualReceivableRepositoryInterface $receivables,
    ) {
    }

    public function execute(int $organizationId, ManualReceivableFilter $filter, int $limit, int $offset): ListManualReceivablesOutput
    {
        return new ListManualReceivablesOutput(
            items: $this->receivables->findByOrganization($organizationId, $filter, $limit, $offset),
            total: $this->receivables->countByOrganization($organizationId, $filter),
            limit: $limit,
            offset: $offset,
        );
    }
}
