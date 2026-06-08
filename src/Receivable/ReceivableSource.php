<?php

declare(strict_types=1);

namespace NeneClear\Receivable;

/**
 * Where a receivable (the target a deposit is reconciled against / dunning
 * targets) comes from (ADR 0014). `invoice_upstream` is owned by NeNe Invoice
 * (Invoice is the system of record; Clear writes payments back). `manual` is
 * entered directly in Clear, which owns it because no other system holds it.
 */
enum ReceivableSource: string
{
    case InvoiceUpstream = 'invoice_upstream';
    case Manual = 'manual';
}
