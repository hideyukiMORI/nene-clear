<?php

declare(strict_types=1);

namespace NeneClear\Organization;

/**
 * The tenant (ADR 0006). `id` is null before persistence.
 */
final readonly class Organization
{
    public function __construct(
        public string $slug,
        public string $name,
        public ?int $id = null,
    ) {
    }
}
