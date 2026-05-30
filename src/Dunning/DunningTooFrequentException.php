<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use RuntimeException;

final class DunningTooFrequentException extends RuntimeException
{
    public function __construct(public readonly string $nextAllowedAt)
    {
        parent::__construct(sprintf('Dunning minimum interval not yet reached. Next allowed at %s.', $nextAllowedAt));
    }
}
