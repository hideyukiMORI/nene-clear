<?php

declare(strict_types=1);

namespace NeneClear\Tests\Dunning;

use NeneClear\ClearSettings\ClearSettings;
use NeneClear\ClearSettings\ClearSettingsRepositoryInterface;

final class InMemoryClearSettingsRepository implements ClearSettingsRepositoryInterface
{
    /** @var array<int, ClearSettings> keyed by orgId */
    private array $byOrg = [];

    public function findByOrganization(int $organizationId): ?ClearSettings
    {
        return $this->byOrg[$organizationId] ?? null;
    }

    public function fiscalYearEndMonth(int $organizationId): ?int
    {
        return ($this->byOrg[$organizationId] ?? null)?->fiscalYearEndMonth;
    }

    public function findOrganizationIdsWithScheduledDunning(): array
    {
        $ids = [];

        foreach ($this->byOrg as $organizationId => $settings) {
            if ($settings->dunningSchedule->isEnabled) {
                $ids[] = $organizationId;
            }
        }

        sort($ids);

        return $ids;
    }

    public function save(ClearSettings $settings): void
    {
        $this->byOrg[$settings->organizationId] = $settings;
    }
}
