<?php

declare(strict_types=1);

namespace NeneClear\Tests\Install;

use Nene2\Install\EnvironmentWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Guards the security properties the installer relies on when it persists `.env`
 * (public_html/install.php → write_env → EnvironmentWriter): the file must never be
 * world-readable and hostile values (`$`, quotes, spaces) must survive a phpdotenv
 * round-trip without breaking the file or injecting extra lines.
 *
 * Mirrors the ordered key map clear's installer writes so a regression in the map or
 * a drift back to a raw `file_put_contents` writer is caught here rather than in prod.
 */
final class EnvFileWriteSecurityTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/nene-clear-env-' . bin2hex(random_bytes(6));

        if (!mkdir($this->dir, 0770, true) && !is_dir($this->dir)) {
            self::fail('could not create the temp directory for the test');
        }
    }

    protected function tearDown(): void
    {
        $env = $this->dir . '/.env';

        if (is_file($env)) {
            @unlink($env);
        }

        @rmdir($this->dir);
    }

    /**
     * The clear installer's mysql env map, mirroring write_env() key order.
     *
     * @return array<string, string>
     */
    private static function clearMysqlEnv(string $dbPassword, string $jwtSecret): array
    {
        return [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_ADAPTER' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '3306',
            'DB_NAME' => 'nene_clear',
            'DB_USER' => 'nene_clear',
            'DB_PASSWORD' => $dbPassword,
            'DB_CHARSET' => 'utf8mb4',
            'NENE_CLEAR_JWT_SECRET' => $jwtSecret,
        ];
    }

    public function testEnvFileIsNotWorldReadable(): void
    {
        $path = $this->dir . '/.env';

        (new EnvironmentWriter())->write(
            $path,
            self::clearMysqlEnv('plain-password', EnvironmentWriter::generateSecret(32)),
        );

        self::assertFileExists($path);

        $perms = fileperms($path);
        self::assertNotFalse($perms);
        // No permission bits for "other"; 0640 is what EnvironmentWriter targets.
        self::assertSame(0, $perms & 0007, sprintf('.env is world-accessible: %o', $perms & 0777));
        self::assertSame(0640, $perms & 0777, sprintf('.env mode is %o, expected 0640', $perms & 0777));
    }

    public function testHostilePasswordIsEscapedAndSurvivesRoundTrip(): void
    {
        $path = $this->dir . '/.env';
        // Contains the three characters phpdotenv treats specially inside quotes ($, ", \),
        // plus a space, a '#' and a `${...}` expansion attempt.
        $password = 'a b"c$d\\e#f${DB_USER}';

        (new EnvironmentWriter())->write(
            $path,
            self::clearMysqlEnv($password, EnvironmentWriter::generateSecret(32)),
        );

        $contents = (string) file_get_contents($path);

        // The raw password must not appear unescaped, and its `$` must be backslash-escaped
        // so phpdotenv does not expand ${DB_USER} into the value.
        self::assertStringContainsString('DB_PASSWORD="', $contents);
        self::assertStringContainsString('\\$', $contents);
        self::assertStringNotContainsString('DB_PASSWORD=a b"c$d', $contents);

        // Round-trip through the same loader the app uses (phpdotenv) to confirm the value
        // is read back verbatim with no injected lines.
        $env = \Dotenv\Dotenv::parse($contents);
        self::assertSame($password, $env['DB_PASSWORD'] ?? null);
        self::assertSame('nene_clear', $env['DB_USER'] ?? null);
        self::assertArrayHasKey('NENE_CLEAR_JWT_SECRET', $env);
        // Exactly the 10 keys we wrote — no extra lines injected via the value.
        self::assertCount(10, $env);
    }

    public function testNewlineInValueIsRefused(): void
    {
        $path = $this->dir . '/.env';

        $this->expectException(RuntimeException::class);

        (new EnvironmentWriter())->write(
            $path,
            self::clearMysqlEnv("secret\nADMIN_OVERRIDE=1", EnvironmentWriter::generateSecret(32)),
        );
    }
}
