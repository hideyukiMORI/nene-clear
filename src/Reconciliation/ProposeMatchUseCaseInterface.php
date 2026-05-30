<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

interface ProposeMatchUseCaseInterface
{
    public function execute(ProposeMatchInput $input): ProposeMatchOutput;
}
