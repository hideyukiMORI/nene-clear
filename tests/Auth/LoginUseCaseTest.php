<?php

declare(strict_types=1);

namespace NeneClear\Tests\Auth;

use NeneClear\Audit\AuditRecorder;
use NeneClear\Auth\InvalidCredentialsException;
use NeneClear\Auth\JwtTokenService;
use NeneClear\Auth\LoginInput;
use NeneClear\Auth\LoginUseCase;
use NeneClear\Auth\Role;
use NeneClear\Tests\Audit\InMemoryAuditEventRepository;
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

    private function useCase(InMemoryUserRepository $users): LoginUseCase
    {
        return new LoginUseCase(
            $users,
            new JwtTokenService(secret: 'test-secret-test-secret-32chars!'),
            new AuditRecorder($this->audit),
            new FixedClock('2026-06-01T10:00:00+00:00'),
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
        self::assertSame('login_succeeded', $this->audit->events[0]->eventType);
        self::assertSame('admin@acme.example', $this->audit->events[0]->payload['after']['email']);
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
        self::assertSame('login_failed', $event->eventType);
        self::assertSame(0, $event->actorUserId);
        self::assertSame('invalid_credentials', $event->payload['after']['failure_reason']);
        self::assertStringNotContainsStringIgnoringCase('wrong-secret', json_encode($event->payload, JSON_THROW_ON_ERROR));
    }
}
