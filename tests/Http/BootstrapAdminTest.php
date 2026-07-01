<?php

declare(strict_types=1);

namespace NeneClear\Tests\Http;

use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\LoginInput;
use NeneClear\Auth\LoginUseCaseInterface;
use NeneClear\Auth\Role;
use NeneClear\Http\ApplicationFactory;
use NeneClear\Http\ServiceResolver;
use NeneClear\Organization\CreateOrganizationInput;
use NeneClear\Organization\CreateOrganizationUseCaseInterface;
use NeneClear\Tests\Support\SchemaFixture;
use NeneClear\User\CreateUserInput;
use NeneClear\User\CreateUserUseCaseInterface;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * The bootstrap path used by tools/create-admin.php: resolve the domain use
 * cases from ApplicationFactory::container(), create the first organization +
 * admin, and confirm the account can immediately log in (proves bcrypt hashing,
 * Active status, and the wiring all line up).
 */
final class BootstrapAdminTest extends TestCase
{
    private const string SECRET = 'test-secret-test-secret-32chars!';
    private const string PASSWORD = 'correct horse battery staple';

    private string $dbPath;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-bootstrap-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        SchemaFixture::createOrganizations($kit->queryExecutor);
        SchemaFixture::createUsers($kit->queryExecutor);
        SchemaFixture::createAuditEvents($kit->queryExecutor);
        SchemaFixture::createTotpTables($kit->queryExecutor);

        $this->container = ApplicationFactory::container($kit->queryExecutor, $kit->transactionManager, self::SECRET);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function testCreatesFirstOrgAndAdminThatCanLogIn(): void
    {
        $organization = ServiceResolver::get($this->container, CreateOrganizationUseCaseInterface::class)
            ->execute(new CreateOrganizationInput(slug: 'acme', name: 'ACME Inc', actorUserId: 0));

        $user = ServiceResolver::get($this->container, CreateUserUseCaseInterface::class)
            ->execute(new CreateUserInput(
                organizationId: $organization->id,
                email: 'admin@acme.example',
                role: Role::Admin,
                password: self::PASSWORD,
                actorUserId: 0,
            ));

        self::assertNotNull($user->id);
        self::assertSame(UserStatus::Active, $user->status);
        self::assertSame(Role::Admin, $user->role);
        self::assertSame($organization->id, $user->organizationId);

        $login = ServiceResolver::get($this->container, LoginUseCaseInterface::class)
            ->execute(new LoginInput('admin@acme.example', self::PASSWORD));

        self::assertIsString($login->token);
        self::assertFalse($login->mfaRequired);
        self::assertSame('admin', $login->role);
        self::assertSame($organization->id, $login->organizationId);
    }

    public function testCreatesCrossTenantSuperadminWithoutAnOrganization(): void
    {
        $user = ServiceResolver::get($this->container, CreateUserUseCaseInterface::class)
            ->execute(new CreateUserInput(
                organizationId: null,
                email: 'root@example.test',
                role: Role::Superadmin,
                password: self::PASSWORD,
                actorUserId: 0,
            ));

        self::assertNull($user->organizationId);
        self::assertSame(UserStatus::Active, $user->status);

        $login = ServiceResolver::get($this->container, LoginUseCaseInterface::class)
            ->execute(new LoginInput('root@example.test', self::PASSWORD));

        self::assertIsString($login->token);
        self::assertSame('superadmin', $login->role);
        self::assertNull($login->organizationId);
    }
}
