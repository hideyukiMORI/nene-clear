<?php

declare(strict_types=1);

namespace NeneClear\Tests\I18n;

use Nene2\Error\ProblemDetailsResponseFactory;
use NeneClear\I18n\LocalizedProblemDetailsFactory;
use NeneClear\I18n\MessageCatalog;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class LocalizedProblemDetailsFactoryTest extends TestCase
{
    private Psr17Factory $psr17;
    private LocalizedProblemDetailsFactory $factory;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
        $this->factory = new LocalizedProblemDetailsFactory(
            new MessageCatalog(dirname(__DIR__, 2) . '/lang'),
            new ProblemDetailsResponseFactory($this->psr17, $this->psr17, 'https://nene-clear.dev/problems/'),
        );
    }

    private function request(?string $acceptLanguage): ServerRequestInterface
    {
        $request = $this->psr17->createServerRequest('GET', '/admin/x');
        if ($acceptLanguage !== null) {
            $request = $request->withHeader('Accept-Language', $acceptLanguage);
        }

        return $request;
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        return (array) json_decode($json, true);
    }

    public function test_no_accept_language_defaults_to_japanese(): void
    {
        $res = $this->factory->create($this->request(null), 'unauthorized', 401);
        $body = $this->decode((string) $res->getBody());

        self::assertSame(401, $res->getStatusCode());
        self::assertSame('認証エラー', $body['title']);
    }

    public function test_explicit_english_uses_english(): void
    {
        $res = $this->factory->create($this->request('en'), 'unauthorized', 401);
        $body = $this->decode((string) $res->getBody());

        self::assertSame('Unauthorized', $body['title']);
    }

    public function test_japanese_preferred_over_english_by_qvalue(): void
    {
        $res = $this->factory->create($this->request('en;q=0.8, ja;q=0.9'), 'unauthorized', 401);
        $body = $this->decode((string) $res->getBody());

        self::assertSame('認証エラー', $body['title']);
    }

    public function test_english_preferred_over_japanese_by_qvalue(): void
    {
        $res = $this->factory->create($this->request('ja;q=0.7, en;q=0.9'), 'unauthorized', 401);
        $body = $this->decode((string) $res->getBody());

        self::assertSame('Unauthorized', $body['title']);
    }

    public function test_unrelated_locale_falls_back_to_japanese(): void
    {
        $res = $this->factory->create($this->request('fr-FR'), 'unauthorized', 401);
        $body = $this->decode((string) $res->getBody());

        self::assertSame('認証エラー', $body['title']);
    }

    public function test_type_uri_includes_slug(): void
    {
        $res = $this->factory->create($this->request('en'), 'bank-account-not-found', 404);
        $body = $this->decode((string) $res->getBody());

        self::assertSame('https://nene-clear.dev/problems/bank-account-not-found', $body['type']);
        self::assertSame(404, $body['status']);
    }

    public function test_custom_detail_key_is_used(): void
    {
        $res = $this->factory->create(
            $this->request('ja'),
            'invalid-state-transition',
            409,
            'problem.invalid-state-transition.batch-reversed.detail',
        );
        $body = $this->decode((string) $res->getBody());

        self::assertSame('バッチはすでに取消済みです。', $body['detail']);
    }

    public function test_create_with_detail_uses_dynamic_detail(): void
    {
        $res = $this->factory->createWithDetail($this->request('ja'), 'validation-failed', 422, 'CSV行3が不正です');
        $body = $this->decode((string) $res->getBody());

        self::assertSame('CSV行3が不正です', $body['detail']);
        self::assertSame('バリデーションエラー', $body['title']);
    }

    public function test_extensions_are_merged_into_payload(): void
    {
        $res = $this->factory->create(
            $this->request('ja'),
            'allocation-exceeds-outstanding',
            422,
            extensions: ['invoice_id' => 123, 'outstanding_cents' => 50000],
        );
        $body = $this->decode((string) $res->getBody());

        self::assertSame(123, $body['invoice_id']);
        self::assertSame(50000, $body['outstanding_cents']);
    }

    public function test_get_returns_translated_string(): void
    {
        self::assertSame(
            'CSVを解析できませんでした: ',
            $this->factory->get($this->request('ja'), 'problem.validation-failed.csv-prefix'),
        );
        self::assertSame(
            'The uploaded CSV could not be parsed: ',
            $this->factory->get($this->request('en'), 'problem.validation-failed.csv-prefix'),
        );
    }
}
