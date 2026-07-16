<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ClientCreditResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ClientCredit $credit): array
    {
        return [
            'client_credit_id' => $credit->id,
            'organization_id' => $credit->organizationId,
            'client_id' => $credit->clientId,
            'client_name' => $credit->clientName,
            'source' => $credit->source->value,
            'manual_receivable_id' => $credit->manualReceivableId,
            'amount_cents' => $credit->amountCents,
            'remaining_cents' => $credit->remainingCents,
            'status' => $credit->status->value,
            'source_bank_transaction_id' => $credit->sourceBankTransactionId,
            'reconciliation_id' => $credit->reconciliationId,
            'created_at' => $credit->createdAt,
        ];
    }
}
