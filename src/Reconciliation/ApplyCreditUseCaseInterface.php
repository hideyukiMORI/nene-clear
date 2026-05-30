<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

interface ApplyCreditUseCaseInterface
{
    public function execute(ApplyCreditInput $input): ApplyCreditOutput;
}
