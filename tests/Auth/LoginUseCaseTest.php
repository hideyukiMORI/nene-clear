<?php

declare(strict_types=1);

namespace NeneClear\Tests\Auth;

use Nene2\Audit\AuditRecorder;
use Nene2\Auth\LocalBearerTokenVerifier;
use Nene2\Auth\TotpAuthenticator as Rfc6238Totp;
use NeneClear\Auth\InvalidCredentialsException;
use NeneClear\Auth\LoginInput;
use NeneClear\Auth\LoginUseCase;
use NeneClear\Auth\MfaChallengeTokens;
use NeneClear\Auth\Role;
use NeneClear\Auth\SessionTokens;
use NeneClear\Mfa\TotpAuthenticator;
use NeneClear\Mfa\TotpSecret;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
use NeneClear\Tests\Mfa\InMemoryTotpSecretRepository;
use NeneClear\Tests\Mfa\InMemoryUsedTotpStepRepository;
use NeneClear\Tests\Support\FixedClock;
use NeneClear\Tests\User\InMemoryUserRepository;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class LoginUseCaseTest extends TestCase
{
    private InMemoryAuditEventRepository $audit;

    protected function setUp(): void
    {
        $this->audit = new InMemoryAuditEventRepository();
    }

    private function useCase(InMemoryUserRepository $users, ?TotpAuthenticator $mfa = null): LoginUseCase
    {
        return new LoginUseCase(
            $users,
            new SessionTokens(
                new LocalBearerTokenVerifier('test-secret-test-secret-32chars!'),
                new FixedClock('2026-06-01T10:00:00+00:00'),
            ),
            new AuditRecorder($this->audit, new FixedClock('2026-06-01T10:00:00+00:00')),
            new FixedClock('2026-06-01T10:00:00+00:00'),
            $mfa ?? new TotpAuthenticator(
                new InMemoryTotpSecretRepository(),
                new InMemoryUsedTotpStepRepository(),
                new Rfc6238Totp(),
                new FixedClock('2026-06-01T10:00:00+00:00'),
            ),
            new MfaChallengeTokens('test-secret-test-secret-32chars!'),
        );
    }

    private function activeUser(string $password): User
    {
        return new User(
            email: 'admin@acme.example',
            role: Role::Admin,
            status: UserStatus::Active,
            passwordHash: password_hash($password, PASSWORD_BCRYPT),
            organizationId: 7,
        );
    }

    public function test_successful_login_returns_token_and_user(): void
    {
        $users = new InMemoryUserRepository([$this->activeUser('correct horse')]);
        $output = $this->useCase($users)->execute(new LoginInput('admin@acme.example', 'correct horse'));

        self::assertNotSame('', $output->token);
        self::assertSame('admin@acme.example', $output->email);
        self::assertSame('admin', $output->role);
        self::assertSame(7, $output->organizationId);
    }

    public function test_login_with_mfa_enabled_returns_a_challenge_not_a_token(): void
    {
        $users = new InMemoryUserRepository([$this->activeUser('correct horse')]);
        $found = $users->findByEmail('admin@acme.example');
        self::assertNotNull($found);
        $userId = (int) $found->id;

        $secrets = new InMemoryTotpSecretRepository();
        $secrets->byUser[$userId] = new TotpSecret($userId, (new Rfc6238Totp())->generateSecret(), isEnabled: true);
        $mfa = new TotpAuthenticator(
            $secrets,
            new InMemoryUsedTotpStepRepository(),
            new Rfc6238Totp(),
            new FixedClock('2026-06-01T10:00:00+00:00'),
        );

        $output = $this->useCase($users, $mfa)->execute(new LoginInput('admin@acme.example', 'correct horse'));

        self::assertTrue($output->mfaRequired);
        self::assertNotNull($output->mfaToken);
        self::assertNull($output->token);
        // login_succeeded is deferred until the second factor is verified.
        self::assertCount(0, $this->audit->events);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $users = new InMemoryUserRepository([$this->activeUser('correct horse')]);

        $this->expectException(InvalidCredentialsException::class);
        $this->useCase($users)->execute(new LoginInput('admin@acme.example', 'wrong'));
    }

    public function test_unknown_email_is_rejected(): void
    {
        $users = new InMemoryUserRepository();

        $this->expectException(InvalidCredentialsException::class);
        $this->useCase($users)->execute(new LoginInput('nobody@acme.example', 'whatever'));
    }

    public function test_invited_user_cannot_log_in(): void
    {
        $invited = new User(
            email: 'invited@acme.example',
            role: Role::Member,
            status: UserStatus::Invited,
            passwordHash: password_hash('correct horse', PASSWORD_BCRYPT),
            organizationId: 7,
        );
        $users = new InMemoryUserRepository([$invited]);

        $this->expectException(InvalidCredentialsException::class);
        $this->useCase($users)->execute(new LoginInput('invited@acme.example', 'correct horse'));
    }

    public function test_successful_login_is_audited(): void
    {
        $users = new InMemoryUserRepository([$this->activeUser('correct horse')]);
        $this->useCase($users)->execute(new LoginInput('admin@acme.example', 'correct horse'));

        self::assertCount(1, $this->audit->events);
        self::assertSame('login_succeeded', $this->audit->events[0]->action);
        self::assertIsArray($this->audit->events[0]->after);
        self::assertSame('admin@acme.example', $this->audit->events[0]->after['email']);
    }

    public function test_failed_login_is_audited_without_leaking_password(): void
    {
        $users = new InMemoryUserRepository([$this->activeUser('correct horse')]);

        try {
            $this->useCase($users)->execute(new LoginInput('admin@acme.example', 'wrong-secret'));
            self::fail('Expected InvalidCredentialsException');
        } catch (InvalidCredentialsException) {
            // expected
        }

        self::assertCount(1, $this->audit->events);
        $event = $this->audit->events[0];
        self::assertSame('login_failed', $event->action);
        self::assertSame(0, $event->actorId);
        self::assertIsArray($event->after);
        self::assertSame('invalid_credentials', $event->after['failure_reason']);
        self::assertStringNotContainsStringIgnoringCase('wrong-secret', json_encode($event->after, JSON_THROW_ON_ERROR));
    }
}
