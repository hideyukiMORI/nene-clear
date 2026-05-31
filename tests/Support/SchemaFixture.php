<?php

declare(strict_types=1);

namespace NeneClear\Tests\Support;

use Nene2\Database\DatabaseQueryExecutorInterface;

/**
 * Creates SQLite test schema inline (the NENE2 test-database strategy: each test
 * builds its own schema). Mirrors the Phinx migrations under database/migrations/.
 */
final class SchemaFixture
{
    public static function createOrganizations(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE organizations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                created_at TEXT,
                updated_at TEXT
            )'
        );
    }

    public static function createUsers(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER,
                email TEXT NOT NULL,
                role TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'active\',
                password_hash TEXT NOT NULL,
                is_deleted INTEGER NOT NULL DEFAULT 0,
                deleted_at TEXT,
                created_at TEXT,
                updated_at TEXT
            )'
        );
    }

    public static function createLoginAttempts(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier_hash TEXT NOT NULL UNIQUE,
                attempt_count INTEGER NOT NULL DEFAULT 0,
                window_started_at TEXT NOT NULL,
                locked_until TEXT,
                created_at TEXT
            )'
        );
    }

    public static function createBankAccounts(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE bank_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                bank_name TEXT NOT NULL,
                bank_branch TEXT NOT NULL DEFAULT \'\',
                account_type TEXT NOT NULL,
                account_number TEXT NOT NULL,
                csv_encoding TEXT NOT NULL DEFAULT \'utf8\',
                csv_date_format TEXT NOT NULL,
                csv_date_column INTEGER NOT NULL,
                csv_amount_column INTEGER NOT NULL,
                csv_counterparty_column INTEGER NOT NULL,
                csv_header_rows INTEGER NOT NULL DEFAULT 1,
                created_at TEXT,
                updated_at TEXT
            )'
        );
    }

    public static function createBankImportBatches(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE bank_import_batches (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                bank_account_id INTEGER NOT NULL,
                file_hash TEXT NOT NULL,
                source_filename TEXT NOT NULL,
                row_count INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT \'imported\',
                imported_by INTEGER NOT NULL,
                imported_at TEXT NOT NULL,
                reversed_at TEXT,
                reversal_reason TEXT,
                created_at TEXT,
                updated_at TEXT,
                UNIQUE (organization_id, file_hash)
            )'
        );
    }

    public static function createBankTransactions(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE bank_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                bank_import_batch_id INTEGER NOT NULL,
                bank_account_id INTEGER NOT NULL,
                value_date TEXT NOT NULL,
                amount_cents INTEGER NOT NULL,
                counterparty_text TEXT NOT NULL DEFAULT \'\',
                line_key TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT \'unmatched\',
                created_at TEXT
            )'
        );
    }

    public static function createAuditEvents(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                event_type TEXT NOT NULL,
                actor_user_id INTEGER NOT NULL,
                occurred_at TEXT NOT NULL,
                payload_json TEXT NOT NULL
            )'
        );
    }

    public static function createPaymentReconciliations(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE payment_reconciliations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                idempotency_key TEXT,
                bank_transaction_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT \'confirmed\',
                reason_code TEXT,
                confirmed_by INTEGER NOT NULL,
                confirmed_at TEXT NOT NULL,
                reversed_at TEXT,
                reversal_reason TEXT,
                created_at TEXT,
                UNIQUE (organization_id, idempotency_key)
            )'
        );
    }

    public static function createReconciliationAllocations(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE reconciliation_allocations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                payment_reconciliation_id INTEGER NOT NULL,
                invoice_id INTEGER NOT NULL,
                amount_cents INTEGER NOT NULL,
                payment_id INTEGER,
                external_reference TEXT,
                created_at TEXT
            )'
        );
    }

    public static function createClientCredits(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE client_credits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                client_id INTEGER NOT NULL,
                amount_cents INTEGER NOT NULL,
                remaining_cents INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT \'open\',
                source_bank_transaction_id INTEGER NOT NULL,
                reconciliation_id INTEGER NOT NULL,
                created_by INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )'
        );
    }

    public static function createClearSettings(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE clear_settings (
                organization_id INTEGER PRIMARY KEY NOT NULL,
                upstream_base_url TEXT NOT NULL DEFAULT \'\',
                upstream_token_ref TEXT NOT NULL DEFAULT \'\',
                dunning_min_interval_days INTEGER NOT NULL DEFAULT 7,
                created_at TEXT,
                updated_at TEXT
            )'
        );
    }

    public static function createDunningNotices(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE dunning_notices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                invoice_id INTEGER NOT NULL,
                invoice_number TEXT NOT NULL,
                client_id INTEGER NOT NULL,
                recipient_email TEXT NOT NULL,
                outstanding_cents INTEGER NOT NULL,
                due_at TEXT NOT NULL,
                channel TEXT NOT NULL DEFAULT \'log\',
                sent_by INTEGER NOT NULL,
                sent_at TEXT NOT NULL,
                created_at TEXT
            )'
        );
    }

    public static function createDunningPauses(DatabaseQueryExecutorInterface $query): void
    {
        $query->execute(
            'CREATE TABLE dunning_pauses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                organization_id INTEGER NOT NULL,
                invoice_id INTEGER NOT NULL,
                paused_by INTEGER NOT NULL,
                paused_at TEXT NOT NULL,
                paused_reason TEXT NOT NULL,
                unpaused_by INTEGER,
                unpaused_at TEXT
            )'
        );
    }
}
