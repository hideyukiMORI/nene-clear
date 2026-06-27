<?php

declare(strict_types=1);

namespace NeneClear\Mfa;

/**
 * Generates and matches one-time recovery codes. Codes are shown to the user
 * once at enrolment and stored only as password hashes (one-way), so a database
 * read never reveals a usable code.
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
        $plain = [];
        $hashes = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            $code = $this->randomCode();
            $plain[] = $code;
            $hashes[] = password_hash($code, PASSWORD_DEFAULT);
        }

        return ['plain' => $plain, 'hashes' => $hashes];
    }

    /**
     * Return the id of the unused code matching $code, or null.
     *
     * @param list<array{id: int, code_hash: string}> $unused
     */
    public function match(string $code, array $unused): ?int
    {
        $code = trim($code);
        foreach ($unused as $row) {
            if (password_verify($code, $row['code_hash'])) {
                return $row['id'];
            }
        }

        return null;
    }

    private function randomCode(): string
    {
        $hex = bin2hex(random_bytes(5)); // 10 hex chars

        return substr($hex, 0, 5) . '-' . substr($hex, 5, 5);
    }
}
