<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

interface ListManualReceivablesUseCaseInterface
{
    public function execute(int $organizationId, ManualReceivableFilter $filter, int $limit, int $offset): ListManualReceivablesOutput;
}
