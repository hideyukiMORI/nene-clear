<?php

declare(strict_types=1);

namespace NeneClear\Auth;

/**
 * Operator roles (ADR 0006). String values are canonical
 * (docs/explanation/terminology.md §2).
 *
 * - superadmin: cross-tenant; manages organizations
 * - admin: single org; manages users, settings, reconciliation, dunning
 * - member: single org; reconciliation operator, may send dunning
 * - viewer: single org; read-only
 */
enum Role: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Superadmin => Capability::cases(),
            self::Admin => [
                Capability::ManageUsers,
                Capability::ManageClearSettings,
                Capability::ManageReconciliation,
                Capability::ViewReconciliation,
                Capability::SendDunning,
            ],
            self::Member => [
                Capability::ManageReconciliation,
                Capability::ViewReconciliation,
                Capability::SendDunning,
            ],
            self::Viewer => [
                Capability::ViewReconciliation,
            ],
        };
    }

    public function has(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    public function isCrossTenant(): bool
    {
        return $this === self::Superadmin;
    }
}
