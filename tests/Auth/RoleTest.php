<?php

declare(strict_types=1);

namespace NeneClear\Tests\Auth;

use NeneClear\Auth\Capability;
use NeneClear\Auth\Role;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_superadmin_has_manage_organizations_and_is_cross_tenant(): void
    {
        self::assertTrue(Role::Superadmin->has(Capability::ManageOrganizations));
        self::assertTrue(Role::Superadmin->isCrossTenant());
    }

    public function test_admin_manages_users_but_not_organizations(): void
    {
        self::assertTrue(Role::Admin->has(Capability::ManageUsers));
        self::assertTrue(Role::Admin->has(Capability::ManageReconciliation));
        self::assertFalse(Role::Admin->has(Capability::ManageOrganizations));
        self::assertFalse(Role::Admin->isCrossTenant());
    }

    public function test_member_can_reconcile_but_not_manage_users(): void
    {
        self::assertTrue(Role::Member->has(Capability::ManageReconciliation));
        self::assertTrue(Role::Member->has(Capability::SendDunning));
        self::assertFalse(Role::Member->has(Capability::ManageUsers));
    }

    public function test_viewer_is_read_only(): void
    {
        self::assertTrue(Role::Viewer->has(Capability::ViewReconciliation));
        self::assertFalse(Role::Viewer->has(Capability::ManageReconciliation));
        self::assertFalse(Role::Viewer->has(Capability::SendDunning));
    }
}
