<?php

declare(strict_types=1);

namespace NeneClear\Tests\Demo;

use Nene2\Auth\TokenIssuerInterface;
use Nene2\Demo\ProvisionedDemoOrg;
use Nene2\Http\UtcClock;
use NeneClear\Auth\Role;
use NeneClear\Auth\SessionTokens;
use NeneClear\Demo\DemoSessionSeater;
use NeneClear\User\User;
use NeneClear\User\UserRepositoryInterface;
use NeneClear\User\UserStatus;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DemoSessionSeaterTest extends TestCase
{
    private function seater(?User $admin): DemoSessionSeater
    {
        $users = new class ($admin) implements UserRepositoryInterface {
            public function __construct(private readonly ?User $admin)
            {
            }

            public function findById(int $id): ?User
            {
                return $this->admin;
            }

            public function findByEmail(string $email): ?User
            {
                return null;
            }

            public function existsByEmail(string $email): bool
            {
                return false;
            }

            public function findAllByOrganization(?int $organizationId, int $limit, int $offset): array
            {
                return [];
            }

            public function countByOrganization(?int $organizationId): int
            {
                return 0;
            }

            public function save(User $user): int
            {
                return 1;
            }

            public function delete(?int $organizationId, int $id, string $deletedAt): void
            {
            }
        };

        $issuer = new class () implements TokenIssuerInterface {
            public function issue(array $claims): string
            {
                return 'demo-jwt-for-' . (is_scalar($claims['sub'] ?? null) ? (string) $claims['sub'] : '0');
            }
        };

        return new DemoSessionSeater($users, new SessionTokens($issuer, new UtcClock()), new Psr17Factory());
    }

    private function request(): \Psr\Http\Message\ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/demo/standard');
    }

    private function admin(): User
    {
        return new User(
            email: 'demo-admin@demo-abc.example',
            role: Role::Admin,
            status: UserStatus::Active,
            passwordHash: 'x',
            organizationId: 42,
            id: 7,
        );
    }

    public function test_seat_page_stores_the_token_and_redirects_into_the_spa(): void
    {
        $response = $this->seater($this->admin())->seatAndRedirect(
            $this->request(),
            new ProvisionedDemoOrg(orgId: 42, slug: 'demo-abc', adminUserId: 7),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));

        $html = (string) $response->getBody();
        self::assertStringContainsString("sessionStorage.setItem('nene_clear_token', \"demo-jwt-for-7\")", $html);
        self::assertStringContainsString("location.replace('/')", $html);
    }

    public function test_page_specific_csp_allows_exactly_the_embedded_nonce(): void
    {
        $response = $this->seater($this->admin())->seatAndRedirect(
            $this->request(),
            new ProvisionedDemoOrg(orgId: 42, slug: 'demo-abc', adminUserId: 7),
        );

        $csp = $response->getHeaderLine('Content-Security-Policy');
        self::assertStringContainsString("default-src 'none'", $csp);
        self::assertSame(1, preg_match("/script-src 'nonce-([^']+)'/", $csp, $m));
        $nonce = $m[1] ?? '';
        self::assertNotSame('', $nonce);

        // The nonce in the policy is the nonce on the script tag.
        self::assertStringContainsString('<script nonce="' . $nonce . '">', (string) $response->getBody());
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function test_nonces_are_unique_per_response(): void
    {
        $seater = $this->seater($this->admin());
        $org = new ProvisionedDemoOrg(orgId: 42, slug: 'demo-abc', adminUserId: 7);

        $first = $seater->seatAndRedirect($this->request(), $org)->getHeaderLine('Content-Security-Policy');
        $second = $seater->seatAndRedirect($this->request(), $org)->getHeaderLine('Content-Security-Policy');

        self::assertNotSame($first, $second);
    }

    public function test_missing_admin_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);

        $this->seater(null)->seatAndRedirect(
            $this->request(),
            new ProvisionedDemoOrg(orgId: 42, slug: 'demo-abc', adminUserId: 7),
        );
    }
}
