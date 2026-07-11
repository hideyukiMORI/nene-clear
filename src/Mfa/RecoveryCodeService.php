<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

use Nene2\Auth\RecoveryCodes;

/**
 * Generates and matches one-time recovery codes on the framework
 * {@see RecoveryCodes} primitive (#292). Codes are shown to the user once at
 * enrolment and stored only as hashes, so a database read never reveals a
 * usable code.
 *
 * New codes carry 80 bits of entropy and are stored as the upstream unsalted
 * SHA-256 hash (safe at that entropy — see the upstream security notes).
 * Codes issued before #292 were 40-bit and bcrypt-hashed; {@see match()}
 * keeps a legacy bcrypt fallback so they stay redeemable until re-enrolment
 * (remove the fallback in a later release).
 */
final readonly class RecoveryCodeService
{
    public const int COUNT = 10;

    /**
     * @return array{plain: list<string>, hashes: list<string>} the plaintext codes
     *         (return to the user once) and their hashes (store)
     */
    public function generate(): array
    {
        $plain = RecoveryCodes::generate(self::COUNT);

        return ['plain' => $plain, 'hashes' => array_map(RecoveryCodes::hash(...), $plain)];
    }

    /**
     * Return the id of the unused code matching $code, or null.
     *
     * @param list<array{id: int, code_hash: string}> $unused
     */
    public function match(string $code, array $unused): ?int
    {
        foreach ($unused as $row) {
            if (RecoveryCodes::verify($code, $row['code_hash'])) {
                return $row['id'];
            }

            // Legacy fallback (#292): pre-migration codes were bcrypt-hashed.
            if (str_starts_with($row['code_hash'], '$2') && password_verify(trim($code), $row['code_hash'])) {
                return $row['id'];
            }
        }

        return null;
    }
}
