<?php

declare(strict_types=1);

namespace NeneClear\Auth;

/**
 * The capabilities a path prefix requires, split by read vs. write so a
 * read-only role (viewer) can list while only a managing role can mutate.
 */
final readonly class CapabilityRule
{
    public function __construct(
        public Capability $read,
        public Capability $write,
    ) {
    }

    /**
     * Same capability for both reads and writes.
     */
    public static function same(Capability $capability): self
    {
        return new self($capability, $capability);
    }
}
