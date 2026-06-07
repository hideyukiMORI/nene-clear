<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

interface ClearSettingsRepositoryInterface
{
    public function findByOrganization(int $organizationId): ?ClearSettings;

    /** Lightweight read of just the fiscal year-end month (1–12), or null if unset/no row. */
    public function fiscalYearEndMonth(int $organizationId): ?int;

    public function save(ClearSettings $settings): void;
}
