<?php

declare(strict_types=1);

namespace NeneClear\ClearSettings;

interface UpdateClearSettingsUseCaseInterface
{
    public function execute(UpdateClearSettingsInput $input): ClearSettings;
}
