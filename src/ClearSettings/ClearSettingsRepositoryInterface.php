<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

interface ClearSettingsRepositoryInterface
{
    public function findByOrganization(int $organizationId): ?ClearSettings;

    public function save(ClearSettings $settings): void;
}
