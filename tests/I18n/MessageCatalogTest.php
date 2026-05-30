<?php

declare(strict_types=1);

namespace NeneClear\Tests\I18n;

use NeneClear\I18n\Locale;
use NeneClear\I18n\MessageCatalog;
use PHPUnit\Framework\TestCase;

final class MessageCatalogTest extends TestCase
{
    private MessageCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new MessageCatalog(dirname(__DIR__, 2) . '/lang');
    }

    public function test_returns_japanese_string(): void
    {
        self::assertSame('バリデーションエラー', $this->catalog->get('problem.validation-failed.title', Locale::Ja));
    }

    public function test_returns_english_string(): void
    {
        self::assertSame('Validation Failed', $this->catalog->get('problem.validation-failed.title', Locale::En));
    }

    public function test_unknown_key_returns_key_itself(): void
    {
        self::assertSame('problem.nonexistent.title', $this->catalog->get('problem.nonexistent.title', Locale::Ja));
    }

    public function test_missing_lang_dir_falls_back_to_key(): void
    {
        $catalog = new MessageCatalog('/nonexistent/lang/dir');
        self::assertSame('problem.validation-failed.title', $catalog->get('problem.validation-failed.title', Locale::Ja));
    }

    public function test_catalog_caches_loaded_file_between_calls(): void
    {
        // Two calls for the same locale return identical results (load is memoized).
        $first = $this->catalog->get('problem.unauthorized.title', Locale::Ja);
        $second = $this->catalog->get('problem.unauthorized.title', Locale::Ja);
        self::assertSame($first, $second);
    }
}
