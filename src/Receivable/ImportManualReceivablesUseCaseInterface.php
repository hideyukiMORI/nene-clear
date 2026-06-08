<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

interface ImportManualReceivablesUseCaseInterface
{
    public function execute(ImportManualReceivablesInput $input): ImportManualReceivablesOutput;
}
