<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

interface CreateManualReceivableUseCaseInterface
{
    public function execute(CreateManualReceivableInput $input): ManualReceivable;
}
