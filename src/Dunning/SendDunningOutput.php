<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

final readonly class SendDunningOutput
{
    public function __construct(
        public int $dunningNoticeId,
    ) {
    }
}
