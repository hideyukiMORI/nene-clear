<?php

declare(strict_types=1);

namespace NeneClear\BankImport;

final readonly class BankTransactionResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(BankTransaction $transaction): array
    {
        return [
            'bank_transaction_id' => $transaction->id,
            'organization_id' => $transaction->organizationId,
            'bank_import_batch_id' => $transaction->bankImportBatchId,
            'bank_account_id' => $transaction->bankAccountId,
            'value_date' => $transaction->valueDate,
            'amount_cents' => $transaction->amountCents,
            'counterparty_text' => $transaction->counterpartyText,
            'status' => $transaction->status->value,
        ];
    }
}
