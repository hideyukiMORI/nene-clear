<?php

declare(strict_types=1);

namespace NeneClear\User;

/**
 * Invitation token helpers. The raw token is high-entropy and travels only in
 * the e-mail link; the database stores `hash()` of it (terminology.md:
 * `token_hash`). Centralised here so the create and accept paths agree on the
 * algorithm.
 */
final readonly class InvitationToken
{
    public static function newRaw(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
