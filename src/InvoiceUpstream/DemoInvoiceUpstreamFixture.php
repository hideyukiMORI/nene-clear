<?php

declare(strict_types=1);

namespace NeneClear\InvoiceUpstream;

use DateTimeImmutable;

/**
 * Populates a {@see FakeInvoiceUpstreamClient} with a small, T-relative set of
 * demo invoices/clients so a standalone demo deployment (no live NeNe Invoice)
 * can walk the full showcase — upstream match suggestions and live dunning
 * sends — without touching auth or the real upstream client (#260).
 *
 * Enabled from the config boundary (`public_html/index.php`) via the
 * `NENE_CLEAR_DEMO_UPSTREAM` env flag; never active by default. Due dates are
 * computed relative to "today" at request time, so the fixture never goes
 * stale. The client is rebuilt per request (in-memory), which means payments
 * written back on a confirmed match do not persist across requests — Clear's
 * own reconciliation records do. `tools/seed-demo.php` seeds dunning history
 * against the same invoice ids/numbers so both views stay coherent.
 *
 * Statuses use the registered upstream `invoice.status` vocabulary only
 * (`issued`, `partially_paid` — terminology registry §2); "overdue" is a
 * computed condition (`issued` + past due), matching dunning eligibility.
 */
final readonly class DemoInvoiceUpstreamFixture
{
    /**
     * Rows: invoiceId, invoiceNumber, clientId, clientName, contactName,
     * recipientEmail, totalCents, outstandingCents, dueInDays (relative to T),
     * status.
     *
     * @return list<array{int, string, int, string, string, string, int, int, int, string}>
     */
    public static function rows(): array
    {
        return [
            [2056, 'INV-2026-056', 156, 'アクメ株式会社', '経理部 佐藤様', 'billing@acme.example', 193000000, 193000000, -24, 'issued'],
            [2057, 'INV-2026-057', 157, '株式会社テストコーポレーション', '経理部 高橋様', 'billing@testcorp.example', 84700000, 35000000, -10, 'partially_paid'],
            [2058, 'INV-2026-058', 158, '山川商事株式会社', '経理課 田村様', 'billing@yamakawa.example', 66000000, 66000000, 6, 'issued'],
            [2059, 'INV-2026-059', 159, 'グリーンフィールド株式会社', '管理部 中野様', 'billing@greenfield.example', 209000000, 209000000, 20, 'issued'],
            [2060, 'INV-2026-060', 160, 'あおぞら建設株式会社', '経理部 藤井様', 'billing@aozora.example', 55000000, 33000000, -31, 'partially_paid'],
            [2061, 'INV-2026-061', 161, '株式会社スターリング', '経理担当 大森様', 'billing@sterling.example', 96800000, 96800000, 45, 'issued'],
        ];
    }

    public static function populate(FakeInvoiceUpstreamClient $client, DateTimeImmutable $today): void
    {
        foreach (self::rows() as [$invoiceId, $invoiceNumber, $clientId, , $contactName, $recipientEmail, $totalCents, $outstandingCents, $dueInDays, $status]) {
            $client->addClient(new InvoiceClientInfo(
                clientId: $clientId,
                contactName: $contactName,
                recipientEmail: $recipientEmail,
            ));
            $client->addInvoice(new InvoiceItem(
                invoiceId: $invoiceId,
                invoiceNumber: $invoiceNumber,
                clientId: $clientId,
                outstandingCents: $outstandingCents,
                totalCents: $totalCents,
                dueAt: $today->modify(sprintf('%+d days', $dueInDays))->format('Y-m-d'),
                status: $status,
                currency: 'JPY',
            ));
        }
    }
}
