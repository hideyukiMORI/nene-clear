<?php

declare(strict_types=1);

namespace NeneClear\Tests\Dunning;

use NeneClear\Dunning\DunningMailerInterface;
use NeneClear\Dunning\DunningMailPayload;

final class SpyDunningMailer implements DunningMailerInterface
{
    /** @var list<DunningMailPayload> */
    public array $sent = [];

    public function channel(): string
    {
        return 'email';
    }

    public function send(DunningMailPayload $payload): void
    {
        $this->sent[] = $payload;
    }
}
