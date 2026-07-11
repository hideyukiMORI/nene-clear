<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use NeneClear\Http\JsonRequestBody;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class JsonRequestBodyTest extends TestCase
{
    private function request(string $contentType, string $body): ServerRequestInterface
    {
        $psr17 = new Psr17Factory();

        return $psr17->createServerRequest('POST', '/admin/auth/login')
            ->withHeader('Content-Type', $contentType)
            ->withBody($psr17->createStream($body));
    }

    public function test_json_body_is_decoded_into_the_parsed_body(): void
    {
        $request = JsonRequestBody::normalize($this->request('application/json', '{"email":"a@b.c","password":"x"}'));

        self::assertSame(['email' => 'a@b.c', 'password' => 'x'], $request->getParsedBody());
        // The raw body stream stays readable for handlers that parse it themselves.
        self::assertSame('{"email":"a@b.c","password":"x"}', (string) $request->getBody());
    }

    public function test_charset_suffixed_content_type_is_still_decoded(): void
    {
        $request = JsonRequestBody::normalize($this->request('application/json; charset=utf-8', '{"a":1}'));

        self::assertSame(['a' => 1], $request->getParsedBody());
    }

    public function test_non_json_and_malformed_bodies_are_left_untouched(): void
    {
        $form = JsonRequestBody::normalize($this->request('application/x-www-form-urlencoded', 'a=1'));
        self::assertNull($form->getParsedBody());

        $malformed = JsonRequestBody::normalize($this->request('application/json', '{not json'));
        self::assertNull($malformed->getParsedBody());

        $scalar = JsonRequestBody::normalize($this->request('application/json', '"just-a-string"'));
        self::assertNull($scalar->getParsedBody());
    }
}
