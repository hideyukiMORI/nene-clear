<?php

declare(strict_types=1);

namespace NeneClear\Tests\Mfa;

use Nene2\Auth\TotpAuthenticator as Rfc6238Totp;
use PHPUnit\Framework\TestCase;

/**
 * Wire-compatibility pins for the #292 migration onto the framework RFC 6238
 * primitive: existing enrollments (Base32 secrets provisioned by the deleted
 * NeneClear TotpGenerator, sha1/6 digits/30 s) must keep producing and
 * verifying the exact same codes.
 */
final class TotpWireCompatibilityTest extends TestCase
{
    public function testRfc6238ReferenceVector(): void
    {
        // RFC 6238 Appendix B: secret "12345678901234567890" (ASCII) = Base32
        // GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ; at T=59s (step 1) the SHA-1 code is 287082.
        self::assertSame('287082', (new Rfc6238Totp())->computeCode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 1));
    }

    public function testLegacyComputedCodeStillVerifies(): void
    {
        // The deleted product generator packed the counter as two 32-bit words
        // (pack('N*', 0) . pack('N*', $step)); the upstream primitive uses
        // pack('J'). Identical bytes for any step < 2^32 — pin one exchange.
        $totp = new Rfc6238Totp();
        $secret = $totp->generateSecret();
        $now = 1_750_000_000; // fixed instant
        $step = intdiv($now, 30);

        $legacy = self::legacyComputeCode($secret, $step);

        self::assertSame($legacy, $totp->computeCode($secret, $step));
        self::assertSame($step, $totp->verify($secret, $legacy, $now));
    }

    public function testProvisioningUriKeepsTheClearIssuerShape(): void
    {
        $uri = (new Rfc6238Totp())->provisioningUri('JBSWY3DPEHPK3PXP', 'admin@acme.example', 'NeNe Clear');

        self::assertStringStartsWith('otpauth://totp/NeNe%20Clear:admin%40acme.example?', $uri);
        self::assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        self::assertStringContainsString('issuer=NeNe%20Clear', $uri);
        self::assertStringContainsString('algorithm=SHA1', $uri);
        self::assertStringContainsString('digits=6', $uri);
        self::assertStringContainsString('period=30', $uri);
    }

    /** The deleted NeneClear\Mfa\TotpGenerator::computeCode, verbatim. */
    private static function legacyComputeCode(string $base32Secret, int $timeStep): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $encoded = strtoupper(str_replace([' ', '-'], '', $base32Secret));
        $secret = '';
        $bitBuffer = 0;
        $bitCount = 0;
        foreach (str_split($encoded) as $char) {
            $bitBuffer = ($bitBuffer << 5) | strpos($alphabet, $char);
            $bitCount += 5;
            if ($bitCount >= 8) {
                $bitCount -= 8;
                $secret .= chr(($bitBuffer >> $bitCount) & 0xFF);
            }
        }

        $msg = pack('N*', 0) . pack('N*', $timeStep);
        $hash = hash_hmac('sha1', $msg, $secret, true);
        $offset = ord($hash[19]) & 0x0F;
        $code = ((ord($hash[$offset]) & 0x7F) << 24)
              | ((ord($hash[$offset + 1]) & 0xFF) << 16)
              | ((ord($hash[$offset + 2]) & 0xFF) << 8)
              | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($code % (10 ** 6)), 6, '0', STR_PAD_LEFT);
    }
}
