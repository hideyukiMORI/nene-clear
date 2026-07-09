<?php

declare(strict_types=1);

namespace NeneClear\Tests\Demo;

use Nene2\Demo\SlugConflictException;
use NeneClear\Demo\DemoOrgProvisioner;
use NeneClear\Organization\CreateOrganizationInput;
use NeneClear\Organization\CreateOrganizationOutput;
use NeneClear\Organization\CreateOrganizationUseCaseInterface;
use NeneClear\Organization\OrganizationAlreadyExistsException;
use NeneClear\User\CreateUserInput;
use NeneClear\User\CreateUserUseCaseInterface;
use NeneClear\User\User;
use NeneClear\User\UserStatus;
use PHPUnit\Framework\TestCase;

final class DemoOrgProvisionerTest extends TestCase
{
    /** @param \ArrayObject<int, CreateUserInput> $capturedUsers */
    private function provisioner(bool $slugTaken, \ArrayObject $capturedUsers): DemoOrgProvisioner
    {
        $createOrg = new class ($slugTaken) implements CreateOrganizationUseCaseInterface {
            public function __construct(private readonly bool $slugTaken)
            {
            }

            public function execute(CreateOrganizationInput $input): CreateOrganizationOutput
            {
                if ($this->slugTaken) {
                    throw new OrganizationAlreadyExistsException($input->slug);
                }

                return new CreateOrganizationOutput(id: 55, slug: $input->slug, name: $input->name);
            }
        };

        $createUser = new class ($capturedUsers) implements CreateUserUseCaseInterface {
            /** @param \ArrayObject<int, CreateUserInput> $captured */
            public function __construct(private readonly \ArrayObject $captured)
            {
            }

            public function execute(CreateUserInput $input): User
            {
                $this->captured->append($input);

                return new User(
                    email: $input->email,
                    role: $input->role,
                    status: UserStatus::Active,
                    passwordHash: 'x',
                    organizationId: $input->organizationId,
                    id: 77,
                );
            }
        };

        return new DemoOrgProvisioner($createOrg, $createUser);
    }

    public function test_provisions_org_and_admin_and_reports_their_ids(): void
    {
        $captured = new \ArrayObject();
        $org = $this->provisioner(false, $captured)->provision('demo-ab12cd34', 'standard');

        self::assertSame(55, $org->orgId);
        self::assertSame('demo-ab12cd34', $org->slug);
        self::assertSame(77, $org->adminUserId);

        self::assertCount(1, $captured);
        $input = $captured[0];
        self::assertInstanceOf(CreateUserInput::class, $input);
        self::assertSame('demo-admin@demo-ab12cd34.example', $input->email);
        self::assertSame(55, $input->organizationId);
        // The password is random and long — nobody logs in as a demo admin.
        self::assertGreaterThanOrEqual(32, strlen((string) $input->password));
    }

    public function test_slug_conflicts_map_to_the_framework_exception_for_retry(): void
    {
        $captured = new \ArrayObject();

        $this->expectException(SlugConflictException::class);
        $this->provisioner(true, $captured)->provision('demo-ab12cd34', 'standard');
    }
}
