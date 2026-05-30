<?php

declare(strict_types=1);

namespace NeneClear\I18n;

use Nene2\Error\ProblemDetailsResponseFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Locale-aware wrapper around ProblemDetailsResponseFactory.
 *
 * Resolves locale from the Accept-Language header (Japanese is the default per
 * ADR 0005) and looks up title/detail from the MessageCatalog by slug.
 *
 * Usage in exception handlers:
 *   $this->problemDetails->create($request, 'bank-account-not-found', 404)
 *
 * For handlers sharing a type slug but needing different detail text:
 *   $this->problemDetails->create($request, 'invalid-state-transition', 409, 'problem.invalid-state-transition.batch-reversed.detail')
 *
 * For dynamic detail content (e.g. CSV parse errors):
 *   $this->problemDetails->createWithDetail($request, 'validation-failed', 422, $detail)
 *
 * To read a translated string directly (for building dynamic details):
 *   $this->problemDetails->get($request, 'problem.validation-failed.csv-prefix')
 */
final readonly class LocalizedProblemDetailsFactory
{
    public function __construct(
        private MessageCatalog $catalog,
        private ProblemDetailsResponseFactory $inner,
    ) {
    }

    /**
     * @param string               $detailKey  Catalog key for the detail string.
     *                                          Empty string → 'problem.{slug}.detail' is used.
     * @param array<string, mixed> $extensions RFC 9457 extension members (e.g. invoice_id, outstanding_cents).
     */
    public function create(
        ServerRequestInterface $request,
        string $slug,
        int $statusCode,
        string $detailKey = '',
        array $extensions = [],
    ): ResponseInterface {
        $locale = $this->resolveLocale($request);
        $title = $this->catalog->get("problem.{$slug}.title", $locale);
        $key = $detailKey !== '' ? $detailKey : "problem.{$slug}.detail";
        $detail = $this->catalog->get($key, $locale);

        return $this->inner->create($request, $slug, $title, $statusCode, $detail, $extensions);
    }

    /**
     * Title from catalog, detail provided directly (use for dynamic content).
     *
     * @param array<string, mixed> $extensions RFC 9457 extension members.
     */
    public function createWithDetail(
        ServerRequestInterface $request,
        string $slug,
        int $statusCode,
        string $detail,
        array $extensions = [],
    ): ResponseInterface {
        $locale = $this->resolveLocale($request);
        $title = $this->catalog->get("problem.{$slug}.title", $locale);

        return $this->inner->create($request, $slug, $title, $statusCode, $detail, $extensions);
    }

    /**
     * Returns a translated string for the given catalog key (use to build dynamic detail).
     */
    public function get(ServerRequestInterface $request, string $key): string
    {
        return $this->catalog->get($key, $this->resolveLocale($request));
    }

    private function resolveLocale(ServerRequestInterface $request): Locale
    {
        $raw = strtolower(trim($request->getHeaderLine('Accept-Language')));

        if ($raw === '') {
            return Locale::Ja;
        }

        // Parse quality values: prefer 'en' only when its q-value exceeds 'ja'
        preg_match_all('/([a-z]{2})(?:[a-z-]*)(?:;q=([0-9.]+))?/', $raw, $m, PREG_SET_ORDER);

        $qJa = 0.0;
        $qEn = 0.0;

        foreach ($m as $match) {
            $lang = $match[1];
            $q = $match[2] ?? '';
            $quality = ($q !== '') ? (float) $q : 1.0;
            if ($lang === 'ja') {
                $qJa = max($qJa, $quality);
            } elseif ($lang === 'en') {
                $qEn = max($qEn, $quality);
            }
        }

        // Japanese is default; switch to English only when explicitly preferred
        if ($qEn > 0.0 && $qEn > $qJa) {
            return Locale::En;
        }

        return Locale::Ja;
    }
}
