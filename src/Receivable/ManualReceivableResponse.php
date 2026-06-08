<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

/**
 * Maps a {@see ManualReceivable} to its public JSON shape (terminology.md /
 * openapi.yaml). `source` is the constant `manual` so a unified receivable view
 * can tell Clear-owned receivables from Invoice-sourced ones (ADR 0014).
 */
final readonly class ManualReceivableResponse
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ManualReceivable $receivable): array
    {
        return [
            'manual_receivable_id' => $receivable->id,
            'organization_id' => $receivable->organizationId,
            'source' => 'manual',
            'reference_number' => $receivable->referenceNumber,
            'client_name' => $receivable->clientName,
            'recipient_email' => $receivable->recipientEmail,
            'total_cents' => $receivable->totalCents,
            'outstanding_cents' => $receivable->outstandingCents,
            'currency' => $receivable->currency,
            'issued_at' => $receivable->issuedAt,
            'due_at' => $receivable->dueAt,
            'status' => $receivable->status->value,
            'created_at' => $receivable->createdAt,
            'updated_at' => $receivable->updatedAt,
        ];
    }
}
