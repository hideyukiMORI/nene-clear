<?php

declare(strict_types=1);

namespace NeneClear\Auth;

/**
 * Reconciliation/dunning capabilities (ADR 0006). String values are canonical
 * (docs/explanation/terminology.md §2) and stable.
 */
enum Capability: string
{
    case ManageOrganizations = 'manage_organizations';
    case ManageUsers = 'manage_users';
    case ManageClearSettings = 'manage_clear_settings';
    case ManageReconciliation = 'manage_reconciliation';
    case ViewReconciliation = 'view_reconciliation';
    case SendDunning = 'send_dunning';
}
