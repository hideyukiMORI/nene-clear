<?php

declare(strict_types=1);

namespace NeneClear\Demo;

use Nene2\Demo\DisposableOrgProvisionerInterface;
use Nene2\Demo\ProvisionedDemoOrg;
use Nene2\Demo\SlugConflictException;
use NeneClear\Auth\Role;
use NeneClear\Organization\CreateOrganizationInput;
use NeneClear\Organization\CreateOrganizationUseCaseInterface;
use NeneClear\Organization\OrganizationAlreadyExistsException;
use NeneClear\User\CreateUserInput;
use NeneClear\User\CreateUserUseCaseInterface;

/**
 * Creates one disposable demo organization + its seated admin (`Nene2\Demo`
 * consumer, #275) through the same audited use cases the HTTP app uses — no
 * auth code, no raw user INSERTs, bcrypt/uniqueness/audit identical to a real
 * tenant. The admin's password is random and never disclosed: the demo
 * session is seated by minting an access token directly
 * ({@see DemoSessionSeater}), so nobody can (or needs to) log in as a demo
 * admin from the login form.
 */
final readonly class DemoOrgProvisioner implements DisposableOrgProvisionerInterface
{
    public function __construct(
        private CreateOrganizationUseCaseInterface $createOrganization,
        private CreateUserUseCaseInterface $createUser,
    ) {
    }

    public function provision(string $slug, string $template): ProvisionedDemoOrg
    {
        try {
            $org = $this->createOrganization->execute(new CreateOrganizationInput(
                slug: $slug,
                name: 'デモ商事株式会社（お試し）',
                actorUserId: 0,
            ));
        } catch (OrganizationAlreadyExistsException $exception) {
            throw new SlugConflictException($exception->getMessage(), previous: $exception);
        }

        $admin = $this->createUser->execute(new CreateUserInput(
            organizationId: $org->id,
            email: 'demo-admin@' . $slug . '.example',
            role: Role::Admin,
            password: bin2hex(random_bytes(16)),
            actorUserId: 0,
        ));

        return new ProvisionedDemoOrg(
            orgId: $org->id,
            slug: $slug,
            adminUserId: (int) ($admin->id ?? 0),
        );
    }
}
