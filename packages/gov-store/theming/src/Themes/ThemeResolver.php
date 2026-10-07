<?php

namespace GovStore\Theming\Themes;

use GovStore\Theming\Models\ThemeAssignment;
use GovStore\Theming\Models\ThemePreference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The resolution chain (§12.2):
 *   1 preview (Lab only) → 2 enforced, top-down (organisation → company → office) →
 *   3 user preference → 4 office → 5 company → 6 organisation → 7 config → 8 default.
 * Unknown, draft (except preview) and deprecated themes fall through to the next step.
 */
final class ThemeResolver
{
    public function __construct(private ThemeRepository $themes) {}

    /**
     * @return array{key: string, mode: string, source: string, enforced_by: ?string, preference: ?array}
     */
    public function resolve(?object $user, ?int $companyId, ?int $locationId, ?array $preview = null): array
    {
        $preference = $user ? $this->preference((int) $user->id) : null;
        $mode = $preview['mode'] ?? null;
        $mode = in_array($mode, ThemePreference::MODES, true) ? $mode : ($preference['mode'] ?? 'system');

        if ($preview && $this->themes->find($preview['key'] ?? null)) {
            return $this->result($preview['key'], $mode, 'preview', null, $preference);
        }

        if ($user) {
            if ($enforced = $this->enforced($companyId, $locationId)) {
                return $this->result($enforced['theme'], $mode, 'enforced', $enforced['scope_type'], $preference);
            }
            if (config('gs-theme.allow_user_choice') && $this->themes->isSelectable($preference['theme'] ?? null)) {
                return $this->result($preference['theme'], $mode, 'user', null, $preference);
            }
            foreach ([['office', $locationId], ['company', $companyId]] as [$scope, $id]) {
                $assignment = $id ? $this->assignment($scope, $id) : null;
                if ($assignment && $this->themes->isSelectable($assignment['theme'])) {
                    return $this->result($assignment['theme'], $mode, $scope, null, $preference);
                }
            }
        }

        return $this->fallback($mode, $preference);
    }

    /** The default a user would get without a personal preference (steps 2, 4–8). */
    public function inheritedDefault(?int $companyId, ?int $locationId): array
    {
        if ($enforced = $this->enforced($companyId, $locationId)) {
            return ['key' => $enforced['theme'], 'source' => $enforced['scope_type'], 'enforced' => true];
        }
        foreach ([['office', $locationId], ['company', $companyId], ['organization', null]] as [$scope, $id]) {
            if ($scope !== 'organization' && ! $id) {
                continue;
            }
            $assignment = $this->assignment($scope, $id);
            if ($assignment && $this->themes->isSelectable($assignment['theme'])) {
                return ['key' => $assignment['theme'], 'source' => $scope, 'enforced' => false];
            }
        }
        $fallback = $this->fallback('system', null);

        return ['key' => $fallback['key'], 'source' => $fallback['source'], 'enforced' => false];
    }

    /** Effective theme for a scope, as an administrator of that scope sees it ("inherits X from Y"). */
    public function effectiveForScope(string $scope, ?int $scopeId, ?int $parentCompanyId = null): array
    {
        $own = $this->assignment($scope, $scopeId);
        if ($own && $this->themes->isSelectable($own['theme'])) {
            return ['key' => $own['theme'], 'source' => $scope, 'own' => true];
        }
        $chain = match ($scope) {
            'office' => [['company', $parentCompanyId], ['organization', null]],
            'company' => [['organization', null]],
            default => [],
        };
        foreach ($chain as [$parentScope, $id]) {
            if ($parentScope !== 'organization' && ! $id) {
                continue;
            }
            $assignment = $this->assignment($parentScope, $id);
            if ($assignment && $this->themes->isSelectable($assignment['theme'])) {
                return ['key' => $assignment['theme'], 'source' => $parentScope, 'own' => false];
            }
        }
        $fallback = $this->fallback('system', null);

        return ['key' => $fallback['key'], 'source' => $fallback['source'], 'own' => false];
    }

    /** First enforced assignment, most senior first. */
    public function enforced(?int $companyId, ?int $locationId): ?array
    {
        foreach ([['organization', null], ['company', $companyId], ['office', $locationId]] as [$scope, $id]) {
            if ($scope !== 'organization' && ! $id) {
                continue;
            }
            $assignment = $this->assignment($scope, $id);
            if ($assignment && $assignment['enforced'] && $this->themes->isSelectable($assignment['theme'])) {
                return $assignment;
            }
        }

        return null;
    }

    public function assignment(string $scope, ?int $id): ?array
    {
        try {
            $row = Cache::remember(ThemeAssignment::cacheKey($scope, $id), (int) config('gs-theme.cache_ttl', 3600), function () use ($scope, $id) {
                $query = ThemeAssignment::query()->where('scope_type', $scope);
                $scope === 'organization' ? $query->whereNull('scope_id') : $query->where('scope_id', $id);
                $assignment = $query->first();

                return $assignment ? $assignment->only(['id', 'scope_type', 'scope_id', 'theme', 'enforced', 'updated_by', 'updated_at']) : false;
            });

            return $row ?: null;
        } catch (Throwable $e) {
            $this->warn($e);

            return null;
        }
    }

    public static function forgetAssignment(string $scope, ?int $id): void
    {
        Cache::forget(ThemeAssignment::cacheKey($scope, $id));
    }

    public function preference(int $userId): ?array
    {
        try {
            return ThemePreference::query()->where('user_id', $userId)->first()?->only(['theme', 'mode']);
        } catch (Throwable $e) {
            $this->warn($e);

            return null;
        }
    }

    private function fallback(string $mode, ?array $preference): array
    {
        $organization = $this->assignment('organization', null);
        if ($organization && $this->themes->isSelectable($organization['theme'])) {
            return $this->result($organization['theme'], $mode, 'organization', null, $preference);
        }
        $configured = config('gs-theme.default');
        if ($this->themes->isSelectable($configured)) {
            return $this->result($configured, $mode, 'config', null, $preference);
        }

        return $this->result('default', $mode, 'default', null, $preference);
    }

    private function result(string $key, string $mode, string $source, ?string $enforcedBy, ?array $preference): array
    {
        return ['key' => $key, 'mode' => $mode, 'source' => $source, 'enforced_by' => $enforcedBy, 'preference' => $preference];
    }

    private function warn(Throwable $e): void
    {
        static $warned = false;
        if (! $warned) {
            $warned = true;
            Log::warning('gs-theme: theme preferences unavailable, using defaults', ['error' => $e->getMessage()]);
        }
    }
}
