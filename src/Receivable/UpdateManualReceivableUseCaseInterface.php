<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

interface UpdateManualReceivableUseCaseInterface
{
    public function execute(UpdateManualReceivableInput $input): ManualReceivable;
}
