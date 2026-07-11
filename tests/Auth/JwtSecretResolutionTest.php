<?php

declare(strict_types=1);

namespace NeneClear\Tests\Auth;

use Nene2\Config\AppEnvironment;
use NeneClear\Auth\AuthServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pins the fail-close JWT-secret resolution semantics (#285): an unresolvable
 * secret yields null (→ the composition root keeps the admin surface
 * unmounted, health-only), the development secret needs an explicit opt-in,
 * and production never honours the development path.
 */
final class JwtSecretResolutionTest extends TestCase
{
    public function test_configured_secret_always_wins(): void
    {
        self::assertSame(
            'operator-configured-secret-32chars',
            AuthServiceProvider::resolveJwtSecret('operator-configured-secret-32chars', AppEnvironment::Production, false),
        );
    }

    public function test_unset_secret_without_opt_in_fails_closed(): void
    {
        self::assertNull(AuthServiceProvider::resolveJwtSecret('', AppEnvironment::Local, false));
    }

    public function test_dev_opt_in_resolves_a_secret_in_local(): void
    {
        $secret = AuthServiceProvider::resolveJwtSecret('', AppEnvironment::Local, true);

        self::assertNotNull($secret);
        self::assertNotSame('', $secret);
    }

    public function test_production_ignores_the_dev_opt_in(): void
    {
        self::assertNull(AuthServiceProvider::resolveJwtSecret('', AppEnvironment::Production, true));
    }
}
