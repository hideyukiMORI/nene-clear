<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

interface CancelManualReceivableUseCaseInterface
{
    public function execute(int $id, int $callerOrganizationId, int $actorUserId): ManualReceivable;
}
