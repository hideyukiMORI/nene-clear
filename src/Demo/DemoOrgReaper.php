<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Demo\DisposableOrgReaperInterface;

/**
 * Destroys one disposable demo organization and everything it owns
 * (`Nene2\Demo` consumer, #275 — the teardown half of `tools/sweep-demo.php`
 * and the sweeper's overflow/TTL path).
 *
 * Child rows are disposable demo data, so they are bulk-DELETEd here first
 * (children → parents; the framework deliberately does not cascade — design
 * doc risk 1), including the per-user TOTP tables that key on `user_id`
 * rather than `organization_id`. The audit trail of a disposable org is part
 * of its demo data and dies with it. `login_attempts` is identifier-hashed
 * (no org column) and stays untouched.
 *
 * The org row itself is deleted directly (hard delete): the audited
 * DeleteOrganization use case soft-deletes and records an audit event, but a
 * reaped org's audit rows are removed in the same pass, so routing through it
 * would only leave an orphaned tombstone. Idempotent by contract — reaping an
 * org that a concurrent sweep already removed is success.
 */
final readonly class DemoOrgReaper implements DisposableOrgReaperInterface
{
    /** Child tables carrying `organization_id` directly, children first. */
    private const array CHILD_TABLES = [
        'dunning_pauses',
        'dunning_notices',
        'reconciliation_allocations',
        'payment_reconciliations',
        'client_credits',
        'bank_transactions',
        'bank_import_batches',
        'bank_accounts',
        'manual_receivables',
        'clear_settings',
        'user_invitations',
        'audit_events',
    ];

    /** Per-user tables keyed on `user_id` (TOTP enrollment state). */
    private const array USER_CHILD_TABLES = ['used_totp_steps', 'recovery_codes', 'totp_secrets'];

    public function __construct(
        private DatabaseQueryExecutorInterface $query,
    ) {
    }

    public function reap(int $orgId): void
    {
        foreach (self::CHILD_TABLES as $table) {
            $this->query->execute("DELETE FROM {$table} WHERE organization_id = ?", [$orgId]);
        }

        foreach (self::USER_CHILD_TABLES as $table) {
            $this->query->execute(
                "DELETE FROM {$table} WHERE user_id IN (SELECT id FROM users WHERE organization_id = ?)",
                [$orgId],
            );
        }

        $this->query->execute('DELETE FROM users WHERE organization_id = ?', [$orgId]);
        $this->query->execute('DELETE FROM organizations WHERE id = ?', [$orgId]);
    }
}
