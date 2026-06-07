<?php

declare(strict_types=1);

namespace NeneClear\User;

/**
 * Builds the absolute accept-invite link placed in the invitation e-mail. The
 * SPA serves `/accept-invite` as a public route (outside the auth shell). The
 * base URL is configured per deployment (`NENE_CLEAR_APP_URL`); when empty the
 * link is site-relative, which is fine for same-origin production.
 */
final readonly class InvitationLinkBuilder
{
    public function __construct(
        private string $baseUrl,
    ) {
    }

    public function forToken(string $rawToken): string
    {
        return rtrim($this->baseUrl, '/') . '/accept-invite?token=' . $rawToken;
    }
}
