<?php

declare(strict_types=1);

namespace NeneClear\Reconciliation;

final readonly class ReconciliationResponse
{
    /**
     * @param list<ReconciliationAllocation> $allocations
     * @return array<string, mixed>
     */
    public static function toArray(Reconciliation $reconciliation, array $allocations = []): array
    {
        return [
            'payment_reconciliation_id' => $reconciliation->id,
            'organization_id' => $reconciliation->organizationId,
            'bank_transaction_id' => $reconciliation->bankTransactionId,
            'status' => $reconciliation->status->value,
            'reason_code' => $reconciliation->reasonCode,
            'confirmed_by' => $reconciliation->confirmedBy,
            'confirmed_at' => $reconciliation->confirmedAt,
            'reversed_at' => $reconciliation->reversedAt,
            'reversal_reason' => $reconciliation->reversalReason,
            'allocations' => array_map(
                static fn (ReconciliationAllocation $a): array => [
                    'reconciliation_allocation_id' => $a->id,
                    'invoice_id' => $a->invoiceId,
                    'amount_cents' => $a->amountCents,
                    'payment_id' => $a->paymentId,
                    'external_reference' => $a->externalReference,
                ],
                $allocations,
            ),
        ];
    }
}
