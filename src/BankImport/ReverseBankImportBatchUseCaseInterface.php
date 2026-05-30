<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

interface ReverseBankImportBatchUseCaseInterface
{
    public function execute(ReverseBankImportBatchInput $input): ReverseBankImportBatchOutput;
}
