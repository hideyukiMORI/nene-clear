<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

interface ImportBankCsvUseCaseInterface
{
    /**
     * @throws BankAccountNotFoundException
     * @throws DuplicateBankImportException
     * @throws InvalidBankCsvException
     */
    public function execute(ImportBankCsvInput $input): ImportBankCsvOutput;
}
