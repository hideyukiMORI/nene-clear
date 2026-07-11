<?php

declare(strict_types=1);

namespace NeneClear\Tests\Mfa;

use Nene2\Auth\TotpAuthenticator as Rfc6238Totp;
use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Http\UtcClock;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Mfa\PdoTotpSecretRepository;
use NeneClear\Mfa\PdoUsedTotpStepRepository;
use NeneClear\Mfa\TotpAuthenticator;
use NeneClear\Mfa\TotpInvalidCodeException;
use NeneClear\Mfa\TotpLockedException;
use NeneClear\Mfa\TotpNotEnabledException;
use NeneClear\Mfa\TotpSecret;
use NeneClear\Security\Encryptor;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class TotpAuthenticatorTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;
    private Rfc6238Totp $gen;
    private PdoTotpSecretRepository $secrets;
    private TotpAuthenticator $auth;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-totp-', true) . '.sqlite';
        $this->query = DatabaseTestKit::sqlite($this->dbPath)->queryExecutor;
        SchemaFixture::createTotpTables($this->query);

        $this->gen = new Rfc6238Totp();
        $key = base64_encode(str_repeat("\x05", SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $this->secrets = new PdoTotpSecretRepository($this->query, new Encryptor($key));
        $this->auth = new TotpAuthenticator(
            $this->secrets,
            new PdoUsedTotpStepRepository($this->query),
            $this->gen,
            new UtcClock(),
        );
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    private function enroll(string $secret): void
    {
        $this->secrets->upsert(new TotpSecret(userId: 1, secret: $secret, isEnabled: true), '2026-06-27 00:00:00');
    }

    public function testVerifiesAValidCodeAndStoresTheSecretEncrypted(): void
    {
        $secret = $this->gen->generateSecret();
        $this->enroll($secret);

        $stored = $this->query->fetchOne('SELECT secret FROM totp_secrets WHERE user_id = 1');
        self::assertIsArray($stored);
        self::assertStringStartsWith('enc:v1:', (string) $stored['secret']);
        self::assertStringNotContainsString($secret, (string) $stored['secret']);

        $this->auth->verify(1, $this->gen->computeCode($secret, $this->gen->currentTimeStep()));
        self::assertTrue($this->auth->isEnabled(1));
    }

    public function testRejectsReplayOfTheSameCode(): void
    {
        $secret = $this->gen->generateSecret();
        $this->enroll($secret);
        $code = $this->gen->computeCode($secret, $this->gen->currentTimeStep());

        $this->auth->verify(1, $code); // first use OK

        $this->expectException(TotpInvalidCodeException::class);
        $this->auth->verify(1, $code); // replay rejected
    }

    public function testLocksAfterThreeFailuresAndThenRejectsEvenAValidCode(): void
    {
        $secret = $this->gen->generateSecret();
        $this->enroll($secret);

        for ($i = 0; $i < 3; $i++) {
            try {
                $this->auth->verify(1, 'xxxxxx'); // non-numeric, never matches
            } catch (TotpInvalidCodeException) {
            }
        }

        $this->expectException(TotpLockedException::class);
        $this->auth->verify(1, $this->gen->computeCode($secret, $this->gen->currentTimeStep()));
    }

    public function testVerifyOnUnenrolledUserThrowsNotEnabled(): void
    {
        $this->expectException(TotpNotEnabledException::class);
        $this->auth->verify(99, '123456');
    }
}
