<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

interface ConfirmMatchUseCaseInterface
{
    public function execute(ConfirmMatchInput $input): ConfirmMatchOutput;
}
