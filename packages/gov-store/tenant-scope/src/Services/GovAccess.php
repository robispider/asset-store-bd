<?php

namespace GovStore\TenantScope\Services;

use GovStore\TenantScope\Contracts\MembershipContextResolver;
use GovStore\TenantScope\Contracts\OrganizationContextResolver;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class GovAccess
{
    public function __construct(private TenantContext $context) {}

    public function roles($user): array
    {
        if (! $user) {
            return [];
        }
        $roles = ['authenticated'];
        if ($user->isSuperUser()) {
            $roles[] = 'superuser';
        }
        $organization = app(OrganizationContextResolver::class);
        if ($this->context->companyId && $organization->isCompanyAdministrator((int) $user->id, $this->context->companyId)) {
            $roles[] = 'company_admin';
        }
        if ($organization->hasJurisdiction((int) $user->id)) {
            $roles[] = 'ict_officer';
        }
        if ($this->context->locationId) {
            if ($organization->isOfficeAdministrator((int) $user->id, $this->context->locationId)) {
                $roles[] = 'office_admin';
            }
            $roles = array_merge($roles, app(MembershipContextResolver::class)->responsibilityRoles((int) $user->id, $this->context->locationId));
            $roles = array_merge($roles, DB::table('gov_access_grants')
                ->where('location_id', $this->context->locationId)->where('user_id', $user->id)
                ->where('expires_at', '>', now())->pluck('role_slug')->all());
        }

        return array_values(array_unique($roles));
    }

    public function decide($user, string $ability, ?array $resolvedRoles = null): AccessDecision
    {
        // Config keys contain periods, so use the whole array rather than dotted lookup.
        $definition = config('govstore-abilities', [])[$ability] ?? null;
        if (! $definition || ! $user) {
            return new AccessDecision(false, $ability, 'unknown_ability');
        }
        $roles = $resolvedRoles ?? $this->roles($user);
        if ($this->outsideInventoryBoundary($definition)) {
            return new AccessDecision(false, $ability, 'role_required', $roles);
        }
        $allowed = in_array('superuser', $roles) || (bool) array_intersect($roles, $definition['roles']);

        return new AccessDecision($allowed, $ability, $allowed ? 'allowed' : 'role_required', $roles);
    }

    public function enforces(string $ability): bool
    {
        $definition = config('govstore-abilities', [])[$ability] ?? [];

        return $this->outsideInventoryBoundary($definition) || ($definition['national'] ?? false) || ($definition['enforce'] ?? false)
            || config('govstore-access.mode') !== 'shadow';
    }

    private function outsideInventoryBoundary(array $definition): bool
    {
        // Object scope enforces in shadow too. Support jurisdictions do not grant
        // an inventory office even when an operational URL is requested directly.
        return ($definition['inventory'] ?? false) && $this->context->isActive
            && ! $this->context->isGlobal && ! $this->context->canUseInventoryOffice();
    }

    public function permitsRequest($user, string $ability): bool
    {
        return $this->decide($user, $ability)->allowed || (! $this->enforces($ability)
            && isset(config('govstore-abilities', [])[$ability]));
    }

    public function qualifier($user, string|array $qualifiers, bool $strict = false, ?array $resolvedRoles = null): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperUser() && ! $strict) {
            return true;
        }
        foreach ((array) $qualifiers as $qualifier) {
            if (isset(config('govstore-abilities', [])[$qualifier])) {
                if ($this->decide($user, $qualifier, $resolvedRoles)->allowed) {
                    return true;
                }

                continue;
            }
            if ($qualifier === 'admin' && ($user->isSuperUser() || $user->hasAccess('admin'))) {
                return true;
            }
            if ($qualifier === 'superuser' && $user->isSuperUser()) {
                return true;
            }
            if ($qualifier === 'approver' && array_intersect($resolvedRoles ?? $this->roles($user), ['primary_approver', 'final_approver'])) {
                return true;
            }
            if (in_array($qualifier, ['office_admin', 'ict_officer', 'company_admin', 'storekeeper'])) {
                if (in_array($qualifier, $resolvedRoles ?? $this->roles($user))) {
                    return true;
                }

                continue;
            }
            if (in_array($qualifier, ['project_head', 'project_officer', 'project_member'])) {
                $designations = match ($qualifier) {
                    'project_head' => ['HEAD'], 'project_officer' => ['OFFICER'], default => ['HEAD', 'OFFICER', 'SUPPORT'],
                };
                if (DB::table('gov_tracking_operation_units')->where('user_id', $user->id)->whereIn('designation', $designations)->exists()) {
                    return true;
                }

                continue;
            }
            if ($this->context->hasPermission($qualifier)) {
                return true;
            }
        }

        return false;
    }

    public function helpers(string $ability): array
    {
        // Never enumerate national administrators or users in other offices.
        if (! $this->context->locationId || (config('govstore-abilities', [])[$ability]['national'] ?? false)) {
            return [];
        }
        $roles = config('govstore-abilities', [])[$ability]['roles'] ?? [];
        $ids = collect(app(MembershipContextResolver::class)->responsibilityUserIds($this->context->locationId, $roles));
        $ids = $ids->merge(DB::table('gov_access_grants')->where('location_id', $this->context->locationId)
            ->whereIn('role_slug', $roles)->where('expires_at', '>', now())->pluck('user_id'));
        // Office admins can help with role requests even when they cannot perform the action.
        $ids = $ids->merge(app(OrganizationContextResolver::class)->officeAdministratorIds($this->context->locationId));

        return DB::table('users')->whereIn('id', $ids->filter()->unique())->where('id', '!=', auth()->id())->whereNull('deleted_at')
            ->get(['id', 'first_name', 'last_name'])->map(fn ($user) => ['name' => trim($user->first_name.' '.$user->last_name)])->all();
    }

    public function payload(AccessDecision $decision, string $reference): array
    {
        return [
            'ability' => $decision->ability,
            'action' => __('tenantops::access.abilities.'.str_replace('.', '_', $decision->ability)),
            'reason_key' => $decision->reason,
            'reason' => __('tenantops::access.'.$decision->reason),
            'helpers' => $this->helpers($decision->ability),
            'next_step' => __('tenantops::access.'.((config('govstore-abilities', [])[$decision->ability]['national'] ?? false) ? 'national_next_step' : 'next_step')),
            'reference_id' => $reference,
            'access_url' => route('gov.access.index', ['ability' => $decision->ability]),
        ];
    }
}
