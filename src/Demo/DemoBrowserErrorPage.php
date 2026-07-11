<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use Nene2\Demo\DemoErrorPageRendererInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

/**
 * NeNe Clear's branded browser error page for the public demo start route
 * (`Nene2\Demo` consumer, #296 — same card as invoice #617 / deal / vault).
 *
 * `GET /demo/{template}` is the one route real people open in a browser (the
 * referral link handed to prospects — the tax-accountant funnel on
 * clear.ayane.co.jp), so its errors must not surface as raw RFC 9457 JSON — a
 * non-technical visitor reads that as "the site is broken". The content
 * negotiation itself lives in the framework
 * ({@see \Nene2\Demo\StartDisposableDemoHandler}, NENE2 ADR 0018): when the
 * client prefers `text/html`, the handler replaces the Problem Details error
 * with the page produced by this renderer, and itself enforces the transport
 * invariants (original status, `Retry-After` copy, `X-Robots-Tag: noindex`).
 * API-shaped clients and the success seat page stay byte-identical.
 *
 * This implementation replaces the framework's unbranded
 * {@see \Nene2\Demo\MinimalDemoErrorPageRenderer} with a self-contained error
 * card in Clear's navy ledger theme (`frontend/src/styles/design.css` ramp):
 * brand header strip, reason callout, and — for 429 — a live countdown that
 * enables the retry button when the window resets.
 *
 * The page carries its own `Content-Security-Policy` allowing its inline
 * style/script ({@see \Nene2\Middleware\SecurityHeadersMiddleware} only adds
 * the app-wide `default-src 'self'` when the header is absent, which would
 * block both). Unlike the framework default's CSP, `script-src` must be
 * allowed here because of the countdown script. That is safe precisely
 * because the renderer contract forbids echoing request input — all copy is
 * fixed text plus numbers computed server-side.
 */
final readonly class DemoBrowserErrorPage implements DemoErrorPageRendererInterface
{
    private const string SELF_HOST_URL = 'https://github.com/hideyukiMORI/nene-clear';

    /**
     * Inline style/script must be allowed for this one self-contained page;
     * everything else stays locked down. No request input is ever echoed.
     */
    private const string CSP = "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src data:; base-uri 'none'; form-action 'none'";

    public function __construct(
        private Psr17Factory $responseFactory,
        private int $throttleLimit,
        private int $throttleWindowSeconds,
    ) {
    }

    public function render(int $statusCode, ?int $retryAfterSeconds): ResponseInterface
    {
        $page = $this->responseFactory->createResponse($statusCode)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Content-Security-Policy', self::CSP);

        $page->getBody()->write($this->html($statusCode, $retryAfterSeconds ?? 0));

        return $page;
    }

    private function html(int $status, int $retryAfter): string
    {
        [$title, $lead] = $this->copyFor($status);

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeLead = htmlspecialchars($lead, ENT_QUOTES, 'UTF-8');
        $css = $this->css();
        $reason = $this->reasonBlock($status);
        $wait = $status === 429 && $retryAfter > 0 ? $this->countdownBlock($retryAfter) : '';
        $actions = $this->actionsBlock($status, $retryAfter);
        $refCode = $status . ' · ' . $this->refCode($status);
        $script = $status === 429 && $retryAfter > 0 ? $this->countdownScript($retryAfter) : '';
        $selfHostUrl = self::SELF_HOST_URL;

        return <<<HTML
            <!DOCTYPE html>
            <html lang="ja">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex">
            <title>{$safeTitle} — NeNe Clear デモ</title>
            <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 42 42'%3E%3Crect width='42' height='42' fill='%230f2540'/%3E%3Ctext x='-2' y='32' font-family='sans-serif' font-weight='800' font-size='32' fill='%23ffffff' opacity='0.4'%3EN%3C/text%3E%3Ctext x='11' y='32' font-family='sans-serif' font-weight='800' font-size='32' fill='%23ffffff'%3EN%3C/text%3E%3C/svg%3E">
            <style>{$css}</style>
            </head>
            <body>
            <main class="err" role="alert" aria-live="polite">
              <div class="err-top">
                <span class="mono-mark" aria-hidden="true"><svg viewBox="0 0 42 42"><text x="-2" y="31" font-family="sans-serif" font-weight="800" font-size="32" fill="currentColor" opacity="0.4">N</text><text x="11" y="31" font-family="sans-serif" font-weight="800" font-size="32" fill="currentColor">N</text></svg></span>
                <div class="et-name">
                  <b>NeNe Clear</b>
                  <span>お試しデモ</span>
                </div>
              </div>
              <div class="err-body">
                <div class="err-ico" aria-hidden="true">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M12 7.5V12l3 2"></path>
                  </svg>
                </div>
                <h1>{$safeTitle}</h1>
                <p class="lead">{$safeLead}</p>
            {$reason}{$wait}{$actions}
                <div class="alt">または</div>
                <div class="self">
                  <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="1.5"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path></svg>
                  <div class="st"><b>待たずに、自分の環境で。</b> NeNe Clear はオープンソース（MIT）。自社サーバに置けば回数制限なくご利用いただけます。<a href="{$selfHostUrl}" rel="noopener">製品情報・セットアップを見る →</a></div>
                </div>
              </div>
              <div class="err-foot">
                <span>NeNe Clear — 入金消込・督促管理</span>
                <span class="ref">{$refCode}</span>
              </div>
            </main>
            {$script}
            </body>
            </html>
            HTML;
    }

    /** @return array{0: string, 1: string} [title, lead] */
    private function copyFor(int $status): array
    {
        return match ($status) {
            429 => [
                'デモのご利用が集中しています',
                '現在、多くの方がデモをお試しいただいています。少し時間をおいてから、もう一度お開きください。',
            ],
            503 => [
                'ただいまデモが満席です',
                'お試し用のデモ環境が上限に達しています。古いデモは毎時自動的に整理されますので、しばらくしてからもう一度お開きください。',
            ],
            404 => [
                'このデモは現在ご利用いただけません',
                'リンクの綴りが変わったか、この環境ではデモが無効になっています。お手数ですが、案内元のリンクをもう一度ご確認ください。',
            ],
            default => [
                'デモを開始できませんでした',
                '一時的な問題が発生しました。しばらくしてからもう一度このリンクを開いてください。',
            ],
        };
    }

    private function reasonBlock(int $status): string
    {
        $windowHours = intdiv($this->throttleWindowSeconds, 3600);
        $windowLabel = $windowHours >= 1 ? "{$windowHours}時間" : intdiv($this->throttleWindowSeconds, 60) . '分';

        $text = match ($status) {
            429 => "サーバー保護のため、同一ネットワーク（IP）からのデモ開始は <b>{$windowLabel}あたり{$this->throttleLimit}回まで</b> に制限しています。この上限に達すると、一定時間おいて自動的に解除されます。",
            503 => 'サーバー保護のため、同時に存在できるデモ環境の数に上限を設けています。古いデモ環境が整理されて空きができると、自動的にご利用いただけるようになります。',
            default => '',
        };

        if ($text === '') {
            return '';
        }

        return <<<HTML
                <div class="reason">
                  <svg aria-hidden="true" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2l5 2v3.5c0 3-2 5.3-5 6.5-3-1.2-5-3.5-5-6.5V4z"></path></svg>
                  <div class="rz">{$text}</div>
                </div>

            HTML;
    }

    private function countdownBlock(int $retryAfter): string
    {
        $clock = $this->formatClock($retryAfter);

        return <<<HTML
                <div class="wait">
                  <div class="wl">
                    再度お試しいただける目安
                    <small>制限時間が経過すると自動で解除されます</small>
                  </div>
                  <div class="clock" id="clock">{$clock}</div>
                </div>

            HTML;
    }

    private function actionsBlock(int $status, int $retryAfter): string
    {
        if ($status === 404) {
            return '';
        }

        $disabled = $status === 429 && $retryAfter > 0 ? ' disabled' : '';

        return <<<HTML
                <div class="err-actions">
                  <button class="btn btn-primary btn-block" id="retry" type="button"{$disabled}>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 4v4h4"></path></svg>
                    もう一度デモを開く
                  </button>
                </div>

            HTML;
    }

    private function countdownScript(int $retryAfter): string
    {
        // The reload re-requests /demo/{template}, which mints a fresh org —
        // exactly the demo's "reset to initial state" affordance.
        return <<<HTML
            <script>
            (function () {
              var remain = {$retryAfter};
              var clock = document.getElementById('clock');
              var retry = document.getElementById('retry');
              retry.addEventListener('click', function () { location.reload(); });
              function render() {
                if (remain <= 0) {
                  clock.textContent = '00:00';
                  clock.classList.add('is-done');
                  retry.disabled = false;
                  return true;
                }
                var m = Math.floor(remain / 60);
                var s = remain % 60;
                clock.textContent = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
                return false;
              }
              render();
              var timer = setInterval(function () {
                remain -= 1;
                if (render()) clearInterval(timer);
              }, 1000);
            })();
            </script>
            HTML;
    }

    private function formatClock(int $seconds): string
    {
        $m = intdiv(max(0, $seconds), 60);
        $s = max(0, $seconds) % 60;

        return sprintf('%02d:%02d', $m, $s);
    }

    private function refCode(int $status): string
    {
        return match ($status) {
            429 => 'DEMO_RATE_LIMIT',
            503 => 'DEMO_CAPACITY',
            404 => 'DEMO_NOT_FOUND',
            default => 'DEMO_ERROR',
        };
    }

    /**
     * Clear's navy ledger palette (the SPA design tokens in
     * `frontend/src/styles/design.css`) over the fleet's proven card layout —
     * kept inline so the page is fully self-contained under its strict CSP.
     */
    private function css(): string
    {
        return <<<'CSS'
            :root {
              --bg: #eef1f6;
              --surface: #ffffff;
              --surface-2: #f6f8fb;
              --surface-sunk: #eef2f8;
              --border: #dce3ec;
              --fg: #19232f;
              --fg-muted: #5a6b80;
              --fg-subtle: #5a6b80;
              --fg-faint: #8595a8;
              --brand: #1e3a5f;
              --brand-strong: #16304f;
              --brand-deep: #0f2540;
              --brand-soft: #e5ecf5;
              --brand-softer: #eef2f8;
              --on-brand: #ffffff;
              --link: #235488;
              --ok: #1f6b4f;
              --warn: #8a5a16;
              --warn-soft: #f7eede;
              --side-brand: #c2d3e8;
              --shadow-pop: 0 8px 28px rgba(10, 28, 48, 0.16), 0 2px 6px rgba(10, 28, 48, 0.08);
              --font-sans: "Noto Sans JP", system-ui, -apple-system, "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
              --font-num: ui-monospace, "SFMono-Regular", "Menlo", "Consolas", monospace;
            }
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            html { font-size: 13px; }
            body {
              font-family: var(--font-sans); color: var(--fg); line-height: 1.5;
              -webkit-font-smoothing: antialiased; font-feature-settings: "palt" 1;
              min-height: 100vh; display: grid; place-items: center;
              padding: 28px 20px;
              background:
                radial-gradient(120% 90% at 50% -10%, var(--brand-softer) 0%, transparent 55%),
                var(--bg);
            }
            a { color: var(--link); text-decoration: none; }
            a:hover { text-decoration: underline; }
            .err {
              width: 100%; max-width: 480px;
              background: var(--surface);
              border: 1px solid var(--border);
              box-shadow: var(--shadow-pop);
              overflow: hidden;
            }
            .err-top {
              position: relative; overflow: hidden;
              display: flex; align-items: center; gap: 12px;
              padding: 18px 26px;
              background: linear-gradient(150deg, var(--brand-strong) 0%, var(--brand-deep) 100%);
              color: #fff;
            }
            .err-top::before {
              content: ""; position: absolute; inset: 0; pointer-events: none;
              background-image:
                linear-gradient(rgba(255, 255, 255, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
              background-size: 34px 34px;
              mask-image: linear-gradient(150deg, #000 10%, transparent 82%);
              -webkit-mask-image: linear-gradient(150deg, #000 10%, transparent 82%);
            }
            .mono-mark { width: 32px; height: 30px; flex: none; display: block; color: var(--side-brand); position: relative; z-index: 1; }
            .mono-mark svg { width: 100%; height: 100%; display: block; }
            .et-name { position: relative; z-index: 1; }
            .et-name b { display: block; font-size: 14.5px; font-weight: 700; letter-spacing: .01em; }
            .et-name span { display: block; font-size: 10px; letter-spacing: .18em; text-transform: uppercase; color: #9db2cc; margin-top: 2px; }
            .err-body { padding: 30px 30px 26px; }
            .err-ico {
              width: 46px; height: 46px; display: grid; place-items: center;
              background: var(--warn-soft); color: var(--warn);
              border: 1px solid #e8d3a8;
              margin-bottom: 18px;
            }
            .err-ico svg { width: 24px; height: 24px; }
            .err-body h1 { font-size: 20px; font-weight: 700; letter-spacing: -.01em; line-height: 1.45; }
            .err-body .lead { font-size: 13px; color: var(--fg-muted); line-height: 1.85; margin-top: 10px; }
            .reason {
              display: flex; gap: 11px; align-items: flex-start; margin-top: 20px;
              padding: 13px 15px;
              background: var(--brand-soft);
              border: 1px solid #c4cedb;
              border-left: 3px solid var(--brand);
            }
            .reason svg { width: 16px; height: 16px; flex: none; margin-top: 1px; color: var(--brand-strong); }
            .reason .rz { font-size: 12px; line-height: 1.7; color: var(--fg); }
            .reason .rz b { font-weight: 700; color: var(--brand-strong); }
            .wait {
              display: flex; align-items: center; justify-content: space-between; gap: 14px;
              margin-top: 18px; padding: 14px 16px;
              background: var(--surface-sunk);
              border: 1px solid var(--border);
            }
            .wait .wl { font-size: 11.5px; color: var(--fg-muted); font-weight: 600; }
            .wait .wl small { display: block; font-size: 10.5px; font-weight: 500; color: var(--fg-faint); margin-top: 3px; }
            .wait .clock {
              font-family: var(--font-num); font-variant-numeric: tabular-nums;
              font-size: 27px; font-weight: 700; letter-spacing: .02em; color: var(--brand-strong);
              line-height: 1;
            }
            .wait .clock.is-done { color: var(--ok); }
            .err-actions { display: flex; flex-direction: column; gap: 9px; margin-top: 22px; }
            .btn {
              display: inline-flex; align-items: center; justify-content: center; gap: 7px;
              font-family: inherit; font-size: 12.5px; font-weight: 600;
              cursor: pointer; padding: 10px 15px; border: 1px solid transparent; line-height: 1;
              transition: background .12s, box-shadow .12s; white-space: nowrap;
            }
            .btn svg { width: 15px; height: 15px; pointer-events: none; }
            .btn-primary { background: var(--brand); color: var(--on-brand); }
            .btn-primary:hover { background: var(--brand-strong); }
            .btn[disabled] { opacity: .5; cursor: not-allowed; }
            .btn-block { width: 100%; }
            .alt { display: flex; align-items: center; gap: 14px; margin: 22px 0 4px; color: var(--fg-faint); font-size: 11px; }
            .alt::before, .alt::after { content: ""; height: 1px; background: var(--border); flex: 1; }
            .self { display: flex; align-items: flex-start; gap: 11px; padding: 4px 2px; }
            .self svg { width: 17px; height: 17px; flex: none; margin-top: 2px; color: var(--brand-strong); }
            .self .st { font-size: 12.5px; line-height: 1.7; color: var(--fg-muted); }
            .self .st b { color: var(--fg); font-weight: 600; }
            .self .st a { font-weight: 600; }
            .err-foot {
              padding: 13px 30px; border-top: 1px solid var(--border);
              background: var(--surface-2);
              font-size: 11px; color: var(--fg-subtle);
              display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
            }
            .err-foot .ref { font-family: var(--font-num); color: var(--fg-faint); }
            @media (max-width: 480px) {
              .err-body { padding: 24px 20px 22px; }
              .err-foot { padding: 12px 20px; }
              .wait .clock { font-size: 23px; }
            }
            CSS;
    }
}
