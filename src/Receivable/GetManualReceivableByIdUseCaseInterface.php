<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

interface GetManualReceivableByIdUseCaseInterface
{
    public function execute(int $id, int $callerOrganizationId): ManualReceivable;
}
