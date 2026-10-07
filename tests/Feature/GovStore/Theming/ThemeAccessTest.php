<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\CapabilityProfileResolver;
use GovStore\TenantScope\Services\EffectivePermissionSet;
use GovStore\Theming\Access\ThemeAccess;
use Illuminate\Support\Facades\Gate;

class ThemeAccessTest extends ThemingTestCase
{
    private function access(): ThemeAccess
    {
        return app(ThemeAccess::class);
    }

    public function test_theming_abilities_are_merged_into_tenant_scope_and_have_gates(): void
    {
        foreach (['theming.appearance.self', 'theming.assign.office', 'theming.assign.company', 'theming.assign.organization', 'theming.lab.view'] as $ability) {
            $this->assertArrayHasKey($ability, config('govstore-abilities'));
            $this->assertTrue(Gate::has($ability), $ability.' has no Gate');
            $this->assertNotSame('tenantops::access.abilities.'.str_replace('.', '_', $ability), __('tenantops::access.abilities.'.str_replace('.', '_', $ability)));
        }
        // Existing tenant-scope abilities are untouched.
        $this->assertArrayHasKey('access.matrix', config('govstore-abilities'));
        $this->assertNotSame('tenantops::access.abilities.access_matrix', __('tenantops::access.abilities.access_matrix'));
    }

    public function test_permission_strings_are_appended_to_capability_profiles(): void
    {
        $profiles = config('govstore-permissions.profiles');
        $this->assertContains('theming.assign.office', $profiles['office_operations']);
        $this->assertContains('theming.assign.company', $profiles['company_operations']);
        $this->assertContains('office.provision', $profiles['company_operations']); // existing entries kept
        $this->assertContains('theming.assign.office', app(CapabilityProfileResolver::class)->resolveSchema('office_admin')->getPermissions());
        foreach ($profiles as $permissions) {
            $this->assertNotContains('theming.lab.view', $permissions);
        }
    }

    public function test_office_admin_may_assign_own_working_office_only(): void
    {
        $user = $this->actor('office_admin');
        $this->assertTrue($this->access()->canAssign($user, 'office', self::LOCATION));
        $this->assertFalse($this->access()->canAssign($user, 'office', 11));
        $this->assertFalse($this->access()->canAssign($user, 'company', self::COMPANY));
        $this->assertFalse($this->access()->canAssign($user, 'organization', null));
        $this->assertFalse($this->access()->canViewLab($user));
        $this->assertSame(['office'], $this->access()->manageableScopes($user));
    }

    public function test_company_admin_may_assign_own_company_only(): void
    {
        $user = $this->actor('company_admin');
        $this->assertTrue($this->access()->canAssign($user, 'company', self::COMPANY));
        $this->assertFalse($this->access()->canAssign($user, 'company', 21));
        $this->assertFalse($this->access()->canAssign($user, 'organization', null));
        $this->assertFalse($this->access()->canViewLab($user));
    }

    public function test_superuser_any_scope_and_lab(): void
    {
        $user = $this->actor('superuser');
        $this->assertTrue($this->access()->canAssign($user, 'office', 11));
        $this->assertTrue($this->access()->canAssign($user, 'company', 21));
        $this->assertTrue($this->access()->canAssign($user, 'organization', null));
        $this->assertTrue($this->access()->canViewLab($user));
        $this->assertSame(['organization', 'company', 'office'], $this->access()->manageableScopes($user));
    }

    public function test_permission_path_grants_choose_only_rights(): void
    {
        $user = $this->actor('employee', 7);
        $context = app(TenantContext::class);
        $this->assertFalse($this->access()->canAssign($user, 'office', self::LOCATION));
        $context->effectivePermissions = new EffectivePermissionSet(['theming.assign.office', 'theming.lab.view'], 'custom', 'office_operations');
        $this->assertTrue($this->access()->canAssign($user, 'office', self::LOCATION));
        $this->assertFalse($this->access()->canAssign($user, 'office', 11));
        $this->assertFalse($this->access()->canViewLab($user), 'Lab access never has a permission path');
        $context->companyAdminPermissions = new EffectivePermissionSet(['theming.assign.company']);
        $this->assertTrue($this->access()->canAssign($user, 'company', self::COMPANY));
    }

    public function test_no_other_role_reaches_the_lab(): void
    {
        foreach (['employee', 'storekeeper', 'primary_approver', 'office_admin', 'company_admin'] as $i => $role) {
            $this->assertFalse($this->access()->canViewLab($this->actor($role, 30 + $i)), $role);
        }
    }

    public function test_appearance_self_follows_allow_user_choice(): void
    {
        $user = $this->actor();
        $this->assertTrue($this->access()->allows($user, 'theming.appearance.self'));
        config(['gs-theme.allow_user_choice' => false]);
        $this->assertFalse($this->access()->allows($user, 'theming.appearance.self'));
        $this->assertFalse($this->access()->allows(null, 'theming.appearance.self'));
    }

    public function test_fallback_without_tenant_scope_is_superuser_only(): void
    {
        $access = new class extends ThemeAccess
        {
            protected function tenantScopeAvailable(): bool
            {
                return false;
            }
        };
        $this->assertTrue($access->allows($this->actor('employee', 40), 'theming.appearance.self'));
        $this->assertFalse($access->allows($this->actor('employee', 41), 'theming.assign.office', self::LOCATION));
        $this->assertTrue($access->allows($this->actor('superuser', 42), 'theming.lab.view'));
    }
}
