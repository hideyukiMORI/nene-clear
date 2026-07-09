<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use DateTimeImmutable;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\DemoDataSeederInterface;
use Nene2\Demo\DemoTemplateKeyInterface;
use Nene2\Http\ClockInterface;

/**
 * Seeds one disposable demo org with a compact, T-relative reconciliation
 * dataset (`Nene2\Demo` consumer, #275). Same realism principles as the fixed
 * demo org's `tools/seed-demo.php` (dates relative to today, status spread,
 * the 名義ズレ showcase), scaled to per-request creation: ~3 months of
 * imports, ~60 deposits, 8 manual receivables, dunning history aligned with
 * the live upstream fixture ({@see \NeneClear\InvoiceUpstream\DemoInvoiceUpstreamFixture}).
 *
 * The propose showcase is **pre-staged**: open manual receivables and their
 * exact-amount unmatched deposits (kanji client vs katakana transfer name)
 * already exist, so a visitor lands and can walk 照合 → 消込 → 督促
 * immediately, without uploading a CSV.
 *
 * Contract (framework docblock): every write goes through the ONE injected
 * executor — a second connection would deadlock the SQLite dev target
 * ("database is locked", proven on invoice).
 */
final readonly class DemoDataSeeder implements DemoDataSeederInterface
{
    public function __construct(
        private DatabaseQueryExecutorInterface $query,
        private ClockInterface $clock,
    ) {
    }

    public function seed(int $orgId, DemoTemplateKeyInterface $template): void
    {
        // Single template today; the narrow keeps future keys explicit.
        if (!$template instanceof DemoTemplate) {
            $template = DemoTemplate::tryFromValue($template->value()) ?? DemoTemplate::Standard;
        }

        $today = $this->clock->now()->setTime(0, 0);
        mt_srand(20260710 + $orgId); // deterministic content per org

        $adminId = $this->adminId($orgId);
        $d = static fn (DateTimeImmutable $x): string => $x->format('Y-m-d');
        $days = static fn (int $offset): DateTimeImmutable => $today->modify(sprintf('%+d days', $offset));
        $at = static fn (DateTimeImmutable $day): string => $day->format('Y-m-d') . sprintf(' %02d:%02d:%02d', mt_rand(9, 17), mt_rand(0, 59), mt_rand(0, 59));
        $json = static fn (array $value): string => (string) json_encode($value, JSON_UNESCAPED_UNICODE);

        /** @var list<array{string, string, string, int, ?string, ?string, ?string}> $audit */
        $audit = [];

        // --- settings + bank account -----------------------------------------
        $settingsAt = $at($days(-80));
        $this->query->insert(
            'INSERT INTO clear_settings (organization_id, upstream_base_url, upstream_token_ref, dunning_min_interval_days, fiscal_year_end_month, created_at, updated_at)'
            . ' VALUES (?, ?, ?, 7, 3, ?, ?)',
            [$orgId, 'https://invoice.example', 'NENE_INVOICE_BEARER_TOKEN', $settingsAt, $settingsAt],
        );

        $accountAt = $at($days(-85));
        $accountId = $this->query->insert(
            'INSERT INTO bank_accounts (organization_id, bank_name, bank_branch, account_type, account_number,'
            . ' csv_encoding, csv_date_format, csv_date_column, csv_amount_column, csv_counterparty_column, csv_header_rows, is_deleted, created_at, updated_at)'
            . " VALUES (?, 'みずほ銀行', '渋谷支店', 'ordinary', '1234567', 'utf8', 'Y/m/d', 0, 1, 3, 1, 0, ?, ?)",
            [$orgId, $accountAt, $accountAt],
        );

        // --- payers ------------------------------------------------------------
        $companies = [
            ['（カ）テストコーポレーション', '株式会社テストコーポレーション', 'testcorp'],
            ['カ）アクメ', 'アクメ株式会社', 'acme'],
            ['ヤマカワショウジ（カ', '山川商事株式会社', 'yamakawa'],
            ['グリーンフィールド（カ', 'グリーンフィールド株式会社', 'greenfield'],
            ['アオゾラケンセツ（カ', 'あおぞら建設株式会社', 'aozora'],
            ['フジサワデンキ（カ', '藤沢電機株式会社', 'fujisawa-denki'],
            ['ヒガシヤマショウテン（カ', '東山商店株式会社', 'higashiyama'],
            ['カ）サクラフーズ', 'さくらフーズ株式会社', 'sakura-foods'],
        ];

        // --- 3 monthly imports, ~60 deposits ----------------------------------
        /** @var list<array{id: int, amount: int, date: DateTimeImmutable, company: int, status: string}> $matchable */
        $matchable = [];
        for ($m = 2; $m >= 0; $m--) {
            $monthStart = $today->modify(sprintf('-%d months', $m))->modify('first day of this month');
            $isCurrent = $m === 0;
            $lastDay = $isCurrent ? max(1, (int) $today->format('j') - 1) : (int) $monthStart->modify('last day of this month')->format('j');
            $importedAt = $at($isCurrent ? $today : $monthStart->modify('last day of this month')->modify('+3 days'));
            $rows = $isCurrent ? 16 : 22;

            $batchId = $this->query->insert(
                'INSERT INTO bank_import_batches (organization_id, bank_account_id, file_hash, source_filename, row_count,'
                . ' status, imported_by, imported_at, reversed_at, reversal_reason, created_at, updated_at)'
                . " VALUES (?, ?, ?, ?, ?, 'imported', ?, ?, NULL, NULL, ?, ?)",
                [$orgId, $accountId, hash('sha256', 'demo-' . $orgId . '-' . $m), 'bank_' . $monthStart->format('Ym') . '.csv', $rows, $adminId, $importedAt, $importedAt, $importedAt],
            );
            $audit[] = ['bank_import', 'bank_import_batch', $importedAt, $batchId, null,
                $json(['source_filename' => 'bank_' . $monthStart->format('Ym') . '.csv', 'row_count' => $rows, 'bank_account_id' => $accountId]),
                $json(['bank_import_batch_id' => $batchId])];

            for ($r = 0; $r < $rows; $r++) {
                $companyIdx = mt_rand(0, count($companies) - 1);
                $cents = mt_rand(55, 2950) * 1000 * 100;
                $valueDate = $monthStart->modify(sprintf('+%d days', mt_rand(0, max(0, $lastDay - 1))));
                $roll = mt_rand(1, 100);
                $status = match (true) {
                    $m >= 1 => $roll <= 80 ? 'matched' : ($roll <= 90 ? 'partially_matched' : 'unmatched'),
                    default => $roll <= 15 ? 'matched' : 'unmatched',
                };

                $txId = $this->query->insert(
                    'INSERT INTO bank_transactions (organization_id, bank_import_batch_id, bank_account_id, value_date,'
                    . ' amount_cents, counterparty_text, line_key, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$orgId, $batchId, $accountId, $d($valueDate), $cents, $companies[$companyIdx][0], md5($orgId . '|' . $batchId . '|' . $r), $status, $importedAt],
                );
                if ($status === 'matched' || $status === 'partially_matched') {
                    $matchable[] = ['id' => $txId, 'amount' => $cents, 'date' => $valueDate, 'company' => $companyIdx, 'status' => $status];
                }
            }
        }

        // --- manual receivables ------------------------------------------------
        // 4 settled against real deposits; 3 open ones pre-stage the showcase:
        // exact-amount 名義ズレ, name-in-counterparty, and an overdue row.
        $mrSeq = 0;
        $mkRef = static function () use (&$mrSeq): string {
            $mrSeq++;

            return sprintf('MR-2026-%03d', $mrSeq);
        };
        /** @var array<int, array{mr: int, amount: int}> $manualAllocations */
        $manualAllocations = [];
        foreach (array_slice($matchable, 0, 4) as $tx) {
            $company = $companies[$tx['company']];
            $issuedAt = $tx['date']->modify('-25 days');
            $createdAt = $at($issuedAt);
            $ref = $mkRef();
            $mrId = $this->query->insert(
                'INSERT INTO manual_receivables (organization_id, reference_number, client_name, recipient_email, total_cents,'
                . ' outstanding_cents, currency, issued_at, due_at, status, created_by, created_at, updated_at, is_deleted)'
                . " VALUES (?, ?, ?, ?, ?, 0, 'JPY', ?, ?, 'paid', ?, ?, ?, 0)",
                [$orgId, $ref, $company[1], 'billing@' . $company[2] . '.example', $tx['amount'], $d($issuedAt), $d($tx['date']->modify('-3 days')), $adminId, $createdAt, $createdAt],
            );
            $manualAllocations[$tx['id']] = ['mr' => $mrId, 'amount' => $tx['amount']];
            $audit[] = ['manual_receivable_created', 'manual_receivable', $createdAt, $mrId, null,
                $json(['reference_number' => $ref, 'client_name' => $company[1], 'total_cents' => $tx['amount']]),
                $json(['manual_receivable_id' => $mrId])];
        }

        $showcase = [
            // client_name, email slug, yen, due offset, counterparty for the staged unmatched deposit
            ['株式会社山田工務店', 'yamada-koumuten', 423500, 10, 'ヤマダコウムテン（カ'],   // exact amount only (名義ズレ)
            ['テストコーポレーション', 'testcorp', 255000, 8, '（カ）テストコーポレーション'], // amount + name signal
            ['高橋建設株式会社', 'takahashi-kensetsu', 341000, -15, 'タカハシケンセツ（カ'],  // overdue aging
        ];
        foreach ($showcase as [$client, $slug, $yen, $dueOffset, $counterparty]) {
            $issuedAt = $days($dueOffset - 30);
            $createdAt = $at($issuedAt);
            $ref = $mkRef();
            $mrId = $this->query->insert(
                'INSERT INTO manual_receivables (organization_id, reference_number, client_name, recipient_email, total_cents,'
                . ' outstanding_cents, currency, issued_at, due_at, status, created_by, created_at, updated_at, is_deleted)'
                . " VALUES (?, ?, ?, ?, ?, ?, 'JPY', ?, ?, 'open', ?, ?, ?, 0)",
                [$orgId, $ref, $client, 'billing@' . $slug . '.example', $yen * 100, $yen * 100, $d($issuedAt), $d($days($dueOffset)), $adminId, $createdAt, $createdAt],
            );
            $audit[] = ['manual_receivable_created', 'manual_receivable', $createdAt, $mrId, null,
                $json(['reference_number' => $ref, 'client_name' => $client, 'total_cents' => $yen * 100]),
                $json(['manual_receivable_id' => $mrId])];

            // The staged deposit the visitor will match against this receivable.
            $depositDate = $days(-mt_rand(1, 3));
            $importedAt = $at($depositDate);
            $stagedBatch = $this->query->insert(
                'INSERT INTO bank_import_batches (organization_id, bank_account_id, file_hash, source_filename, row_count,'
                . ' status, imported_by, imported_at, reversed_at, reversal_reason, created_at, updated_at)'
                . " VALUES (?, ?, ?, ?, 1, 'imported', ?, ?, NULL, NULL, ?, ?)",
                [$orgId, $accountId, hash('sha256', 'demo-stage-' . $orgId . '-' . $ref), 'bank_' . $depositDate->format('Ymd') . '.csv', $adminId, $importedAt, $importedAt, $importedAt],
            );
            $this->query->insert(
                'INSERT INTO bank_transactions (organization_id, bank_import_batch_id, bank_account_id, value_date,'
                . " amount_cents, counterparty_text, line_key, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'unmatched', ?)",
                [$orgId, $stagedBatch, $accountId, $d($depositDate), $yen * 100, $counterparty, md5($orgId . '|stage|' . $ref), $importedAt],
            );
            $audit[] = ['bank_import', 'bank_import_batch', $importedAt, $stagedBatch, null,
                $json(['source_filename' => 'bank_' . $depositDate->format('Ymd') . '.csv', 'row_count' => 1, 'bank_account_id' => $accountId]),
                $json(['bank_import_batch_id' => $stagedBatch])];
        }

        // --- reconciliations + allocations + a few credits ---------------------
        $creditCount = 0;
        foreach ($matchable as $i => $tx) {
            $confirmedAt = $at($tx['date']->modify(sprintf('+%d days', mt_rand(1, 5))));
            $reconId = $this->query->insert(
                'INSERT INTO payment_reconciliations (organization_id, bank_transaction_id, status, reason_code,'
                . ' confirmed_by, confirmed_at, reversed_at, reversal_reason, created_at, idempotency_key)'
                . " VALUES (?, ?, 'confirmed', NULL, ?, ?, NULL, NULL, ?, ?)",
                [$orgId, $tx['id'], $adminId, $confirmedAt, $confirmedAt, 'demo-' . $orgId . '-' . $tx['id']],
            );

            $manual = $manualAllocations[$tx['id']] ?? null;
            $withCredit = $manual === null && $tx['status'] === 'matched' && mt_rand(1, 100) <= 12;
            $allocated = match (true) {
                $manual !== null => $manual['amount'],
                $tx['status'] === 'partially_matched' => (int) (round($tx['amount'] * 0.6 / 100000) * 100000),
                $withCredit => $tx['amount'] - mt_rand(5, 40) * 1000 * 100,
                default => $tx['amount'],
            };

            $this->query->insert(
                'INSERT INTO reconciliation_allocations (organization_id, payment_reconciliation_id, invoice_id, amount_cents,'
                . ' payment_id, external_reference, created_at, source, manual_receivable_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orgId, $reconId, $manual !== null ? $manual['mr'] : 2000 + $i, max($allocated, 100000),
                    $manual !== null ? null : 5000 + $i,
                    sprintf('clear:recon:%d:%d', $reconId, $manual !== null ? $manual['mr'] : 2000 + $i),
                    $confirmedAt, $manual !== null ? 'manual' : 'invoice_upstream', $manual !== null ? $manual['mr'] : null,
                ],
            );
            $audit[] = ['reconciliation_confirmed', 'payment_reconciliation', $confirmedAt, $reconId,
                $json(['status' => 'unmatched']),
                $json(['status' => 'confirmed', 'amount_cents' => $tx['amount'], 'allocated_cents' => max($allocated, 100000)]),
                $json(['payment_reconciliation_id' => $reconId, 'bank_transaction_id' => $tx['id']])];

            if ($withCredit) {
                $creditAmount = $tx['amount'] - $allocated;
                $creditId = $this->query->insert(
                    'INSERT INTO client_credits (organization_id, client_id, amount_cents, remaining_cents, status,'
                    . ' source_bank_transaction_id, reconciliation_id, created_by, created_at, source, manual_receivable_id, client_name)'
                    . " VALUES (?, ?, ?, ?, 'open', ?, ?, ?, ?, 'invoice_upstream', NULL, ?)",
                    [$orgId, 100 + $tx['company'], $creditAmount, $creditCount % 2 === 0 ? $creditAmount : 0, $tx['id'], $reconId, $adminId, $confirmedAt, $companies[$tx['company']][1]],
                );
                $creditCount++;
                $audit[] = ['client_credit_applied', 'client_credit', $confirmedAt, $creditId, null,
                    $json(['amount_cents' => $creditAmount, 'status' => 'open']),
                    $json(['client_credit_id' => $creditId, 'bank_transaction_id' => $tx['id']])];
            }
        }

        // --- dunning history aligned with the live upstream fixture ------------
        $notices = [
            [2056, 'INV-2026-056', 156, 'billing@acme.example', 193000000, -40],
            [2057, 'INV-2026-057', 157, 'billing@testcorp.example', 35000000, -2],
            [2060, 'INV-2026-060', 160, 'billing@aozora.example', 33000000, -9],
            [2001, 'INV-2026-002', 101, 'billing@acme.example', 84800000, -22],
            [2002, 'INV-2026-003', 102, 'billing@yamakawa.example', 45700000, -15],
            [2003, 'INV-2026-004', 103, 'billing@greenfield.example', 129000000, -33],
        ];
        foreach ($notices as [$invoiceId, $number, $clientId, $email, $outstanding, $offset]) {
            $sentAt = $at($days($offset));
            $noticeId = $this->query->insert(
                'INSERT INTO dunning_notices (organization_id, invoice_id, invoice_number, client_id, recipient_email,'
                . ' outstanding_cents, due_at, channel, sent_by, sent_at, created_at, template_version)'
                . " VALUES (?, ?, ?, ?, ?, ?, ?, 'email', ?, ?, ?, '1.0')",
                [$orgId, $invoiceId, $number, $clientId, $email, $outstanding, $d($days($offset - 20)), $adminId, $sentAt, $sentAt],
            );
            $audit[] = ['dunning_sent', 'dunning_notice', $sentAt, $noticeId,
                $json(['invoice_status' => 'issued', 'invoice_outstanding_cents' => $outstanding]),
                $json(['invoice_number' => $number, 'recipient_email' => $email, 'outstanding_at_send_cents' => $outstanding, 'channel' => 'email', 'template_version' => '1.0']),
                $json(['dunning_notice_id' => $noticeId, 'invoice_id' => $invoiceId])];
        }

        $pausedAt = $at($days(-6));
        $this->query->insert(
            'INSERT INTO dunning_pauses (organization_id, invoice_id, paused_by, paused_at, paused_reason, unpaused_by, unpaused_at)'
            . ' VALUES (?, 2059, ?, ?, ?, NULL, NULL)',
            [$orgId, $adminId, $pausedAt, '支払計画合意済み（分割・毎月末）'],
        );
        $audit[] = ['dunning_paused', 'invoice', $pausedAt, 2059, $json(['is_paused' => false]),
            $json(['is_paused' => true, 'paused_reason' => '支払計画合意済み（分割・毎月末）']), $json(['invoice_id' => 2059])];

        // --- flush the audit trail chronologically ------------------------------
        usort($audit, static fn (array $a, array $b): int => strcmp($a[2], $b[2]));
        foreach ($audit as [$action, $entityType, $occurredAt, $entityId, $before, $after, $metadata]) {
            $this->query->insert(
                'INSERT INTO audit_events (organization_id, action, actor_id, occurred_at, entity_type, entity_id, before_json, after_json, metadata_json)'
                . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$orgId, $action, $adminId, $occurredAt, $entityType, $entityId, $before, $after, $metadata],
            );
        }
    }

    /** The org's provisioned admin (created just before seed by the provisioner). */
    private function adminId(int $orgId): int
    {
        $row = $this->query->fetchOne(
            "SELECT id FROM users WHERE organization_id = ? AND role = 'admin' ORDER BY id LIMIT 1",
            [$orgId],
        );

        return is_array($row) ? (int) $row['id'] : 0;
    }
}
