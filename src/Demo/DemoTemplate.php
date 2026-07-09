<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use Nene2\Demo\DemoTemplateKeyInterface;

/**
 * The disposable-demo seed templates Clear offers (`Nene2\Demo` consumer,
 * #275). One well-built reconciliation dataset beats several thin ones for
 * this product — Clear's showcase is a single flow (bank deposits →
 * name-mismatch matching → dunning), not per-industry document sets like
 * invoice's. Adding a template means adding a case here, seeding it in
 * {@see DemoDataSeeder}, and listing the exact path in
 * {@see \NeneClear\Auth\AuthServiceProvider} public paths (exact-match
 * blocklist) — the route itself (`/demo/{template}`) already matches.
 */
enum DemoTemplate: string implements DemoTemplateKeyInterface
{
    case Standard = 'standard';

    public static function tryFromValue(string $value): ?static
    {
        return self::tryFrom($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
