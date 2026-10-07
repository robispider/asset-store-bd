<?php

namespace GovStore\Theming\Access;

use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;

/**
 * Role-based (tenant-scope GovAccess) OR permission-based (capability profile strings)
 * decisions for theming.* abilities, plus scope checks (§12.6).
 */
class ThemeAccess
{
    /** Abilities that may be granted through a capability-profile permission string. */
    public const PERMISSION_ABILITIES = ['theming.assign.office', 'theming.assign.company'];

    public const SCOPE_ABILITIES = [
        'organization' => 'theming.assign.organization',
        'company' => 'theming.assign.company',
        'office' => 'theming.assign.office',
    ];

    public function allows(?object $user, string $ability, ?int $scopeId = null, bool $checkScope = true): bool
    {
        if (! $user) {
            return false;
        }
        if ($ability === 'theming.appearance.self' && ! config('gs-theme.allow_user_choice')) {
            // Mode can still be changed; the theme picker is read-only (see AppearanceController).
            return false;
        }
        if (! $this->tenantScopeAvailable()) {
            return $ability === 'theming.appearance.self' || $user->isSuperUser();
        }
        $granted = app(GovAccess::class)->decide($user, $ability)->allowed
            || (in_array($ability, self::PERMISSION_ABILITIES, true) && app(TenantContext::class)->hasPermission($ability));

        return $granted && (! $checkScope || $this->withinScope($user, $ability, $scopeId));
    }

    public function canAssign(?object $user, string $scope, ?int $scopeId): bool
    {
        return isset(self::SCOPE_ABILITIES[$scope]) && $this->allows($user, self::SCOPE_ABILITIES[$scope], $scopeId);
    }

    /** Scopes the user may manage at all (ignoring which id). */
    public function manageableScopes(?object $user): array
    {
        return array_keys(array_filter(self::SCOPE_ABILITIES, fn ($ability) => $this->allows($user, $ability, null, false)));
    }

    public function canViewLab(?object $user): bool
    {
        return (bool) config('gs-theme.lab_enabled') && $this->allows($user, 'theming.lab.view');
    }

    /** True when the user may manage every id of a scope (superuser), not only their own. */
    public function anyScopeId(?object $user): bool
    {
        return (bool) $user?->isSuperUser();
    }

    public function withinScope(object $user, string $ability, ?int $scopeId): bool
    {
        if ($user->isSuperUser()) {
            return true;
        }
        $context = $this->tenantScopeAvailable() ? app(TenantContext::class) : null;

        return match ($ability) {
            'theming.assign.office' => $scopeId !== null && $context?->locationId !== null && $scopeId === (int) $context->locationId,
            'theming.assign.company' => $scopeId !== null && $context?->companyId !== null && $scopeId === (int) $context->companyId,
            'theming.assign.organization' => $scopeId === null,
            default => true,
        };
    }

    protected function tenantScopeAvailable(): bool
    {
        return class_exists(GovAccess::class) && app()->bound(TenantContext::class);
    }
}
