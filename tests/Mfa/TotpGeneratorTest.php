<?php

declare(strict_types=1);

namespace NeneClear\Tests\Mfa;

use NeneClear\Mfa\TotpGenerator;
use PHPUnit\Framework\TestCase;

final class TotpGeneratorTest extends TestCase
{
    public function testGeneratesABase32SecretAndStableCodes(): void
    {
        $gen = new TotpGenerator();
        $secret = $gen->generateSecret();

        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        // Deterministic for a fixed step; 6 digits, zero-padded.
        $code = $gen->computeCode($secret, 12345);
        self::assertSame($code, $gen->computeCode($secret, 12345));
        self::assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
    }

    public function testRfc6238ReferenceVector(): void
    {
        // RFC 6238 Appendix B: secret "12345678901234567890" (ASCII) = Base32
        // GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ; at T=59s (step 1) the SHA-1 code is 287082.
        $gen = new TotpGenerator();
        self::assertSame('287082', $gen->computeCode('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 1));
    }

    public function testVerifyAcceptsCurrentStepAndRejectsWrongCode(): void
    {
        $gen = new TotpGenerator();
        $secret = $gen->generateSecret();
        $step = $gen->currentTimeStep();

        self::assertSame($step, $gen->verify($secret, $gen->computeCode($secret, $step)));
        self::assertNull($gen->verify($secret, 'not-a-code'));
    }

    public function testVerifyToleratesOneStepOfClockSkew(): void
    {
        $gen = new TotpGenerator();
        $secret = $gen->generateSecret();
        $prev = $gen->currentTimeStep() - 1;

        self::assertSame($prev, $gen->verify($secret, $gen->computeCode($secret, $prev), 1));
        self::assertNull($gen->verify($secret, $gen->computeCode($secret, $prev), 0)); // outside window
    }

    public function testOtpAuthUriIsScannable(): void
    {
        $uri = (new TotpGenerator())->buildOtpAuthUri('JBSWY3DPEHPK3PXP', 'admin@acme.example');

        self::assertStringStartsWith('otpauth://totp/NeNe%20Clear:admin%40acme.example?', $uri);
        self::assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        self::assertStringContainsString('issuer=NeNe%20Clear', $uri);
        self::assertStringContainsString('algorithm=SHA1&digits=6&period=30', $uri);
    }
}
