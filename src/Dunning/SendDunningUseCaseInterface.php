<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

interface SendDunningUseCaseInterface
{
    public function execute(SendDunningInput $input): SendDunningOutput;
}
