<?php

declare(strict_types=1);

/**
 * Security-test data seeder. Generates a large, realistic multi-tenant dataset
 * so isolation / IDOR / scoping tests have real cross-tenant data to leak.
 *
 * Usage: DB_* env vars point at the target MySQL; run from repo root.
 *   DB_HOST=127.0.0.1 DB_PORT=3310 DB_NAME=nene_clear DB_USER=nene_clear DB_PASSWORD=nene_clear php tests/security/seed.php
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3310);
$name = getenv('DB_NAME') ?: 'nene_clear';
$user = getenv('DB_USER') ?: 'nene_clear';
$pass = getenv('DB_PASSWORD') ?: 'nene_clear';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Clean slate (children → parents).
foreach ([
    'dunning_notices', 'client_credits', 'reconciliation_allocations',
    'payment_reconciliations', 'bank_transactions', 'bank_import_batches',
    'bank_accounts', 'clear_settings', 'audit_events', 'users', 'organizations',
] as $t) {
    $pdo->exec("DELETE FROM `$t`");
}

$bcrypt = fn (string $p): string => password_hash($p, PASSWORD_BCRYPT);

// Superadmin (cross-tenant, organization_id NULL).
$pdo->prepare('INSERT INTO users (organization_id, email, role, status, password_hash, is_deleted) VALUES (NULL,?,?,?,?,0)')
    ->execute(['root@nene-clear.dev', 'superadmin', 'active', $bcrypt('root-pass-1234')]);

$orgCount = 3;
$report = [];

for ($o = 1; $o <= $orgCount; $o++) {
    $slug = "org$o";
    $pdo->prepare('INSERT INTO organizations (slug, name) VALUES (?,?)')->execute([$slug, "Organization $o"]);
    $orgId = (int) $pdo->lastInsertId();

    // Per-org settings + bank account.
    $pdo->prepare('INSERT INTO clear_settings (organization_id, upstream_base_url, upstream_token_ref, dunning_min_interval_days) VALUES (?,?,?,7)')
        ->execute([$orgId, "https://invoice$o.example", "INVOICE_TOKEN_REF_$o"]);
    $pdo->prepare('INSERT INTO bank_accounts (organization_id, bank_name, bank_branch, account_type, account_number, csv_encoding, csv_date_format, csv_date_column, csv_amount_column, csv_counterparty_column, csv_header_rows) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$orgId, "Bank $o", 'Main', 'ordinary', "00$o-1234567", 'utf8', 'Y/m/d', 0, 1, 3, 1]);
    $bankAccountId = (int) $pdo->lastInsertId();

    // Org admin + members + viewers.
    $pdo->prepare('INSERT INTO users (organization_id, email, role, status, password_hash, is_deleted) VALUES (?,?,?,?,?,0)')
        ->execute([$orgId, "admin@$slug.example", 'admin', 'active', $bcrypt("admin-pass-$o")]);
    $report["org{$o}_admin"] = "admin@$slug.example / admin-pass-$o";
    for ($u = 1; $u <= 5; $u++) {
        $role = $u % 2 === 0 ? 'viewer' : 'member';
        $pdo->prepare('INSERT INTO users (organization_id, email, role, status, password_hash, is_deleted) VALUES (?,?,?,?,?,0)')
            ->execute([$orgId, "user$u@$slug.example", $role, 'active', $bcrypt("pass-$o-$u")]);
    }

    // Import batches + transactions.
    for ($b = 1; $b <= 10; $b++) {
        $pdo->prepare('INSERT INTO bank_import_batches (organization_id, bank_account_id, file_hash, source_filename, row_count, status, imported_by, imported_at) VALUES (?,?,?,?,?,?,?,NOW())')
            ->execute([$orgId, $bankAccountId, hash('sha256', "org$o-batch$b"), "statement-$o-$b.csv", 50, 'imported', 1]);
        $batchId = (int) $pdo->lastInsertId();

        $txStmt = $pdo->prepare('INSERT INTO bank_transactions (organization_id, bank_import_batch_id, bank_account_id, value_date, amount_cents, counterparty_text, line_key, status) VALUES (?,?,?,?,?,?,?,?)');
        for ($t = 1; $t <= 50; $t++) {
            $amount = random_int(1000, 5000000);
            $status = ['unmatched', 'unmatched', 'matched', 'partially_matched'][array_rand([0, 1, 2, 3])];
            $txStmt->execute([
                $orgId, $batchId, $bankAccountId,
                sprintf('2026-%02d-%02d', random_int(1, 12), random_int(1, 28)),
                $amount, "カ）取引先{$o}-{$b}-{$t}",
                hash('md5', "$orgId-$batchId-$t"), $status,
            ]);
        }
    }

    // Reconciliations + allocations.
    $recStmt = $pdo->prepare('INSERT INTO payment_reconciliations (organization_id, idempotency_key, bank_transaction_id, status, confirmed_by, confirmed_at) VALUES (?,?,?,?,?,NOW())');
    $allocStmt = $pdo->prepare('INSERT INTO reconciliation_allocations (organization_id, payment_reconciliation_id, invoice_id, amount_cents, payment_id, external_reference) VALUES (?,?,?,?,?,?)');
    for ($r = 1; $r <= 30; $r++) {
        $recStmt->execute([$orgId, "clear:recon:$orgId:$r", $r, 'confirmed', 1]);
        $recId = (int) $pdo->lastInsertId();
        $allocStmt->execute([$orgId, $recId, 1000 + $r, random_int(1000, 200000), 9000 + $r, "clear:recon:$recId"]);
    }

    // Client credits.
    $crStmt = $pdo->prepare('INSERT INTO client_credits (organization_id, client_id, amount_cents, remaining_cents, status, source_bank_transaction_id, reconciliation_id, created_by, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
    for ($c = 1; $c <= 15; $c++) {
        $amt = random_int(1000, 100000);
        $crStmt->execute([$orgId, 100 + $c, $amt, $amt, 'open', $c, $c, 1]);
    }

    // Dunning history.
    $dnStmt = $pdo->prepare('INSERT INTO dunning_notices (organization_id, invoice_id, invoice_number, client_id, recipient_email, outstanding_cents, due_at, channel, sent_by, sent_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
    for ($d = 1; $d <= 20; $d++) {
        $dnStmt->execute([$orgId, 1000 + $d, "INV-$o-" . str_pad((string) $d, 4, '0', STR_PAD_LEFT), 100 + $d, "client$d@$slug.example", random_int(10000, 500000), '2026-06-30', 'log', 1]);
    }
}

// Counts.
foreach (['organizations', 'users', 'bank_transactions', 'payment_reconciliations', 'client_credits', 'dunning_notices'] as $t) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `$t`");
    $report["count_$t"] = $stmt === false ? 0 : (int) $stmt->fetchColumn();
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
