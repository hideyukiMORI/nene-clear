<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

/**
 * Lifecycle of a manually-entered receivable (ADR 0014). Clear computes this
 * itself (it is the system of record for manual receivables): `open` until a
 * confirmed allocation reduces the balance, `partially_paid` while some remains,
 * `paid` at zero, `cancelled` when the operator voids it. `overdue` is NOT a
 * status — it is derived from `due_at` vs today, like upstream invoices.
 */
enum ManualReceivableStatus: string
{
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
