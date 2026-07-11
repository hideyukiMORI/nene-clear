<?php

declare(strict_types=1);

namespace NeneClear\Tests\Mfa;

use Nene2\Auth\RecoveryCodes;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Mfa\PdoRecoveryCodeRepository;
use NeneClear\Mfa\RecoveryCodeService;
use NeneClear\Tests\Support\SchemaFixture;
use PHPUnit\Framework\TestCase;

final class RecoveryCodeServiceTest extends TestCase
{
    public function testGeneratesTenUniqueCodesWithMatchingHashes(): void
    {
        $r = (new RecoveryCodeService())->generate();

        self::assertCount(RecoveryCodeService::COUNT, $r['plain']);
        self::assertCount(RecoveryCodeService::COUNT, $r['hashes']);
        self::assertCount(RecoveryCodeService::COUNT, array_unique($r['plain']));
        foreach ($r['plain'] as $i => $code) {
            // 80-bit codes hashed with the upstream sha256 scheme (#292).
            self::assertSame(20, strlen(str_replace('-', '', $code)));
            self::assertTrue(RecoveryCodes::verify($code, $r['hashes'][$i]));
        }
    }

    public function testMatchAcceptsALegacyBcryptHashedCode(): void
    {
        // Codes issued before #292 were bcrypt-hashed in the old 2×5-hex shape;
        // they must stay redeemable until the user re-enrolls.
        $svc = new RecoveryCodeService();
        $legacyCode = 'a1b2c-3d4e5';
        $unused = [
            ['id' => 1, 'code_hash' => password_hash('other-code1', PASSWORD_DEFAULT)],
            ['id' => 2, 'code_hash' => password_hash($legacyCode, PASSWORD_DEFAULT)],
        ];

        self::assertSame(2, $svc->match($legacyCode, $unused));
        self::assertSame(2, $svc->match("  {$legacyCode}  ", $unused)); // legacy trim behaviour
        self::assertNull($svc->match('a1b2c-00000', $unused));
    }

    public function testMatchFindsTheCorrectUnusedCodeAndRejectsWrong(): void
    {
        $svc = new RecoveryCodeService();
        $r = $svc->generate();
        $unused = [];
        foreach ($r['hashes'] as $i => $hash) {
            $unused[] = ['id' => $i + 1, 'code_hash' => $hash];
        }

        self::assertSame(3, $svc->match($r['plain'][2], $unused));
        self::assertNull($svc->match('not-a-real-code', $unused));
    }

    public function testRepositoryReplaceFindConsumeRoundTrip(): void
    {
        $dbPath = sys_get_temp_dir() . '/' . uniqid('clear-rcv-', true) . '.sqlite';
        $query = DatabaseTestKit::sqlite($dbPath)->queryExecutor;
        SchemaFixture::createTotpTables($query);

        try {
            $repo = new PdoRecoveryCodeRepository($query);
            $svc = new RecoveryCodeService();
            $r = $svc->generate();

            $repo->replaceForUser(7, $r['hashes'], '2026-06-27 00:00:00');
            self::assertSame(RecoveryCodeService::COUNT, $repo->countUnused(7));

            // Consume one code.
            $unused = $repo->findUnusedByUser(7);
            $id = $svc->match($r['plain'][0], $unused);
            self::assertNotNull($id);
            $repo->markUsed($id, '2026-06-27 01:00:00');

            self::assertSame(RecoveryCodeService::COUNT - 1, $repo->countUnused(7));
            // A consumed code no longer matches the remaining unused set.
            self::assertNull($svc->match($r['plain'][0], $repo->findUnusedByUser(7)));
        } finally {
            if (is_file($dbPath)) {
                unlink($dbPath);
            }
        }
    }
}
