<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

/**
 * A receivable entered directly in Clear, not sourced from the NeNe Invoice
 * upstream (ADR 0014). Because no other system holds it, Clear is its system of
 * record: it owns `outstandingCents` and `status` and computes them itself.
 *
 * It is a reconciliation reference (`referenceNumber` is the external document
 * number from whatever tool issued the invoice) — Clear never issues an invoice,
 * a qualified-invoice PDF, or computes tax (scope-contract X1). `issuedAt` /
 * `dueAt` are calendar dates (`YYYY-MM-DD`) or null; `dueAt` is required before
 * the receivable can be dunned or aged.
 */
final readonly class ManualReceivable
{
    public function __construct(
        public int $organizationId,
        public string $referenceNumber,
        public string $clientName,
        public ?string $recipientEmail,
        public int $totalCents,
        public int $outstandingCents,
        public string $currency,
        public ?string $issuedAt,
        public ?string $dueAt,
        public ManualReceivableStatus $status,
        public int $createdBy,
        public string $createdAt,
        public ?string $updatedAt = null,
        public ?int $id = null,
    ) {
    }
}
