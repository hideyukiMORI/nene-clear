<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

interface ReverseReconciliationUseCaseInterface
{
    public function execute(ReverseReconciliationInput $input): ReverseReconciliationOutput;
}
