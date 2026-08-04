<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

interface ClearSettingsRepositoryInterface
{
    public function findByOrganization(int $organizationId): ?ClearSettings;

    /** Lightweight read of just the fiscal year-end month (1–12), or null if unset/no row. */
    public function fiscalYearEndMonth(int $organizationId): ?int;

    /**
     * Organization ids that have opted in to scheduled dunning (#400 §6), ascending.
     *
     * Asked as one query rather than by walking every organization and reading its
     * settings: the feature is default-off, so on a deployment where nobody has
     * enabled it this must cost one round trip and return nothing — an unattended
     * job that scales its work with the tenant count would get slower forever while
     * doing nothing.
     *
     * @return list<int>
     */
    public function findOrganizationIdsWithScheduledDunning(): array;

    public function save(ClearSettings $settings): void;
}
