<?php

declare(strict_types=1);

namespace NeneClear\Tests\Support;

use DateTimeImmutable;
use Nene2\Http\ClockInterface;

final readonly class FixedClock implements ClockInterface
{
    public function __construct(private string $instant = '2026-05-30T09:00:00+00:00')
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->instant);
    }
}
