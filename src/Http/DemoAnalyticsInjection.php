<?php

declare(strict_types=1);

namespace NeneClear\Http;

/**
 * Env-gated, demo-only injection of a cookieless GoatCounter beacon into the
 * SPA shell `<head>`, with a matching Content-Security-Policy.
 *
 * OSS-release integrity is the load-bearing constraint: the beacon is **never**
 * baked into the committed frontend build or the release ZIP. It is emitted
 * only by the server shell in {@see \NeneClear\Http\SpaFallback} path, and only
 * when `DEMO_ANALYTICS_ENDPOINT` names a valid https origin — the demo HETEML
 * deployment sets it in its own `.env`, while the shipped `.env.example` leaves
 * it empty. A default (OSS) install therefore injects nothing and emits no
 * analytics CSP: the served shell is byte-identical to the built artifact, so
 * self-hosters ship zero telemetry.
 *
 * Self-hosted GoatCounter (`https://stats.ayane.co.jp`) is cookieless — no
 * consent gate, no PII. The single endpoint origin drives both the beacon URLs
 * (`/count.js`, `/count`) and the CSP allow-list; it is validated to a bare
 * https origin so a misconfigured value can neither break the head markup nor
 * silently widen the policy.
 */
final readonly class DemoAnalyticsInjection
{
    private function __construct(private ?string $endpoint)
    {
    }

    /**
     * @param array<string, mixed> $env Environment map (typically `$_ENV`).
     */
    public static function fromEnv(array $env): self
    {
        $raw = is_string($env['DEMO_ANALYTICS_ENDPOINT'] ?? null) ? trim($env['DEMO_ANALYTICS_ENDPOINT']) : '';

        return new self(self::normaliseEndpoint($raw));
    }

    public function isEnabled(): bool
    {
        return $this->endpoint !== null;
    }

    /**
     * Returns the shell HTML with the beacon inserted just before `</head>`.
     * When disabled — or when the shell carries no `</head>` marker — the HTML
     * is returned unchanged (OSS default: no injection).
     */
    public function injectHead(string $html): string
    {
        if ($this->endpoint === null) {
            return $html;
        }

        $pos = stripos($html, '</head>');
        if ($pos === false) {
            return $html;
        }

        return substr($html, 0, $pos) . '    ' . $this->beaconTag() . "\n  " . substr($html, $pos);
    }

    /**
     * The demo-only CSP for the shell: the framework-proven `default-src 'self'`
     * baseline (the sibling SPA runs under it) plus the analytics origin on the
     * directives GoatCounter needs — `count.js` (script), the hit beacon
     * (`navigator.sendBeacon` → connect; `<img>` pixel fallback → img). Returns
     * null when disabled so the OSS shell keeps its current header set unchanged.
     */
    public function contentSecurityPolicy(): ?string
    {
        if ($this->endpoint === null) {
            return null;
        }

        $origin = $this->endpoint;

        return "default-src 'self'; "
            . "script-src 'self' {$origin}; "
            . "connect-src 'self' {$origin}; "
            . "img-src 'self' data: {$origin}; "
            . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'";
    }

    /**
     * The GoatCounter beacon tag. Empty string when disabled.
     */
    public function beaconTag(): string
    {
        if ($this->endpoint === null) {
            return '';
        }

        $count = htmlspecialchars($this->endpoint . '/count', ENT_QUOTES);
        $countJs = htmlspecialchars($this->endpoint . '/count.js', ENT_QUOTES);

        return sprintf('<script data-goatcounter="%s" async src="%s"></script>', $count, $countJs);
    }

    /**
     * Accept only a bare https origin (scheme + host [+ optional port]). Anything
     * with a path, query, fragment, credentials, or unexpected characters is
     * rejected (→ null → disabled) so the value is safe in both an HTML attribute
     * and a response-header context.
     */
    private static function normaliseEndpoint(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        // Defence-in-depth for HTML-attribute + HTTP-header contexts.
        if (preg_match('/[\s"\'<>;]/', $raw) === 1) {
            return null;
        }

        $parts = parse_url($raw);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === '') {
            return null;
        }

        foreach (['path', 'query', 'fragment', 'user', 'pass'] as $unexpected) {
            if (($parts[$unexpected] ?? '') !== '') {
                return null;
            }
        }

        $origin = 'https://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}
