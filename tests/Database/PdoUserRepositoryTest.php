<?php

declare(strict_types=1);

namespace NeneClear\Tests\Database;

use Nene2\Database\DatabaseQueryExecutorInterface;
use Nene2\Testing\DatabaseTestKit;
use NeneClear\Auth\Role;
use NeneClear\Tests\Support\SchemaFixture;
use NeneClear\User\PdoUserRepository;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class PdoUserRepositoryTest extends TestCase
{
    private string $dbPath;
    private DatabaseQueryExecutorInterface $query;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/' . uniqid('clear-user-', true) . '.sqlite';
        $kit = DatabaseTestKit::sqlite($this->dbPath);
        $this->query = $kit->queryExecutor;
        SchemaFixture::createUsers($this->query);
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbPath)) {
            unlink($this->dbPath);
        }
    }

    public function test_save_round_trips_role_status_and_organization(): void
    {
        $repo = new PdoUserRepository($this->query);
        $id = $repo->save(new User(
            email: 'admin@acme.example',
            role: Role::Admin,
            status: UserStatus::Active,
            passwordHash: 'hash',
            organizationId: 7,
        ));

        $user = $repo->findById($id);
        self::assertNotNull($user);
        self::assertSame('admin@acme.example', $user->email);
        self::assertSame(Role::Admin, $user->role);
        self::assertSame(UserStatus::Active, $user->status);
        self::assertSame(7, $user->organizationId);
    }

    public function test_superadmin_has_null_organization(): void
    {
        $repo = new PdoUserRepository($this->query);
        $id = $repo->save(new User(
            email: 'root@platform.example',
            role: Role::Superadmin,
            status: UserStatus::Active,
            passwordHash: 'hash',
            organizationId: null,
        ));

        $user = $repo->findById($id);
        self::assertNotNull($user);
        self::assertNull($user->organizationId);
        self::assertSame(1, $repo->countByOrganization(null));
    }

    public function test_soft_delete_excludes_user_and_find_by_email(): void
    {
        $repo = new PdoUserRepository($this->query);
        $id = $repo->save(new User(
            email: 'member@acme.example',
            role: Role::Member,
            status: UserStatus::Invited,
            passwordHash: 'hash',
            organizationId: 7,
        ));

        self::assertTrue($repo->existsByEmail('member@acme.example'));
        self::assertNotNull($repo->findByEmail('member@acme.example'));

        $repo->delete(7, $id);

        self::assertFalse($repo->existsByEmail('member@acme.example'));
        self::assertNull($repo->findById($id));
        self::assertSame(0, $repo->countByOrganization(7));
    }
}
