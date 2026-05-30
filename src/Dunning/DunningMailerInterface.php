<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

interface DunningMailerInterface
{
    public function send(DunningMailPayload $payload): void;
}
