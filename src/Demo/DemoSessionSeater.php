<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use Nene2\Demo\DemoSessionSeaterInterface;
use Nene2\Demo\ProvisionedDemoOrg;
use NeneClear\Auth\SessionTokens;
use NeneClear\User\UserRepositoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

/**
 * Seats the visitor into a freshly provisioned demo org (`Nene2\Demo`
 * consumer, #275) — the Clear-specific JWT design the framework module left
 * to the product (design doc §3: "JWT bearer での SPA 着地").
 *
 * Clear's SPA keeps its bearer token in `sessionStorage` (`nene_clear_token`)
 * and sends it on every API call — there is no cookie session to set, so the
 * invoice cookie seater does not transplant. Instead the seater mints a
 * normal access token for the demo admin via the app's own
 * {@see SessionTokens} (same claims, TTL and secret as a login) and
 * serves a one-shot **seat page**: a nonce'd inline script stores the token
 * and `location.replace('/')` into the SPA. Auth code is untouched.
 *
 * The page carries its own per-response CSP — `script-src 'nonce-…'` with
 * everything else locked — because the app-wide `default-src 'self'` would
 * block the inline script (the exact trap invoice hit in its #612; the NENE2
 * security-headers middleware only fills headers that are absent, so this
 * page-specific policy wins). The script embeds only server-generated values
 * (JSON-encoded JWT); no request input is echoed.
 *
 * Product-honest constraint carried into the session: the access token
 * expires after the normal TTL (1 h) with no refresh — the demo simply asks
 * for a fresh `/demo/{template}` visit. `sessionStorage` also means the
 * session is per-tab, like any Clear login.
 */
final readonly class DemoSessionSeater implements DemoSessionSeaterInterface
{
    public function __construct(
        private UserRepositoryInterface $users,
        private SessionTokens $tokenIssuer,
        private Psr17Factory $psr17,
    ) {
    }

    public function seatAndRedirect(ServerRequestInterface $request, ProvisionedDemoOrg $org): ResponseInterface
    {
        $admin = $this->users->findById($org->adminUserId);

        if ($admin === null) {
            throw new RuntimeException(sprintf('Provisioned demo admin #%d is missing.', $org->adminUserId));
        }

        $token = $this->tokenIssuer->issueForUser($admin);
        $nonce = base64_encode(random_bytes(18));

        $tokenJs = json_encode($token, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="ja">
        <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>NeNe Clear — デモを準備しています…</title>
        </head>
        <body>
        <noscript><p>デモの開始には JavaScript が必要です。ブラウザの JavaScript を有効にして、もう一度お試しください。</p></noscript>
        <p>デモを準備しています…</p>
        <script nonce="{$nonce}">
        sessionStorage.setItem('nene_clear_token', {$tokenJs});
        location.replace('/');
        </script>
        </body>
        </html>
        HTML;

        $response = $this->psr17->createResponse(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            // Page-specific CSP: allow exactly this one nonce'd script; lock the rest.
            ->withHeader('Content-Security-Policy', "default-src 'none'; script-src 'nonce-{$nonce}'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'")
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Referrer-Policy', 'no-referrer');
        $response->getBody()->write($html);

        return $response;
    }
}
