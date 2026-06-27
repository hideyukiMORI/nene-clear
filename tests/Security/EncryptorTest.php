<?php

declare(strict_types=1);

namespace NeneClear\Tests\Security;

use InvalidArgumentException;
use NeneClear\Security\Encryptor;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EncryptorTest extends TestCase
{
    private static function key(string $byte = "\x01"): string
    {
        return base64_encode(str_repeat($byte, SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public function testRoundTripWithKey(): void
    {
        $enc = new Encryptor(self::key());

        self::assertTrue($enc->isEnabled());
        $cipher = $enc->encrypt('1234567');
        self::assertStringStartsWith('enc:v1:', $cipher);
        self::assertStringNotContainsString('1234567', $cipher);
        self::assertSame('1234567', $enc->decrypt($cipher));
    }

    public function testEachEncryptionUsesAFreshNonce(): void
    {
        $enc = new Encryptor(self::key());

        self::assertNotSame($enc->encrypt('x'), $enc->encrypt('x'));
    }

    public function testPassthroughWhenDisabled(): void
    {
        $enc = new Encryptor(null);

        self::assertFalse($enc->isEnabled());
        self::assertSame('1234567', $enc->encrypt('1234567'));
        self::assertSame('1234567', $enc->decrypt('1234567'));
    }

    public function testDecryptLeavesLegacyPlaintextAlone(): void
    {
        $enc = new Encryptor(self::key());

        self::assertSame('plain-legacy', $enc->decrypt('plain-legacy'));
    }

    public function testRejectsKeyOfWrongLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Encryptor(base64_encode('too-short'));
    }

    public function testDecryptFailsOnTamperedCiphertext(): void
    {
        $enc = new Encryptor(self::key());
        $tampered = $enc->encrypt('secret') . 'AA';

        $this->expectException(RuntimeException::class);
        $enc->decrypt($tampered);
    }

    public function testEncryptedValueIsUnreadableWithTheWrongKey(): void
    {
        $cipher = (new Encryptor(self::key("\x01")))->encrypt('secret');

        $this->expectException(RuntimeException::class);
        (new Encryptor(self::key("\x02")))->decrypt($cipher);
    }
}
