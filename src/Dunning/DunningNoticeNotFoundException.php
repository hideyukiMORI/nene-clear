<?php

declare(strict_types=1);

namespace NeneClear\Dunning;

use RuntimeException;

final class DunningNoticeNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $id)
    {
        parent::__construct(sprintf('Dunning notice %d was not found.', $id));
    }
}
