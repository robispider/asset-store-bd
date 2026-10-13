<?php

namespace GovStore\OfficeMembership\Services;

use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Models\OfficeMembership;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The offices a user may switch their working context to, for the navbar office chip.
 *
 * Members choose among their active memberships; super users may enter any office that has a
 * profile. Super users (and members of many offices) never get the full list on a page: the
 * chip shows recent offices and searches the rest through MembershipController::offices().
 */
final class OfficePicker
{
    /** Members with more offices than this search instead of scrolling. */
    public const INLINE_LIMIT = 8;

    public const RECENT_LIMIT = 5;

    public const PAGE_SIZE = 20;

    /** Active, unexpired memberships with a live office. */
    public function memberships(User $user): Collection
    {
        return OfficeMembership::with(['location.parent', 'location.company'])->where('user_id', $user->id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', today()->toDateString()))
            ->get()
            ->filter(fn ($membership) => $membership->location && ! $membership->location->deleted_at)
            ->sortBy(fn ($membership) => $membership->location->name)
            ->values();
    }

    public function searches(User $user, ?Collection $memberships = null): bool
    {
        return $user->isSuperUser() || ($memberships ?? $this->memberships($user))->count() > self::INLINE_LIMIT;
    }

    /**
     * One page of matching offices as switch targets.
     *
     * @return array{results: array<int, array{field: string, value: int, location_id: int, text: string, sub: string}>, more: bool}
     */
    public function search(User $user, string $term, int $page = 1): array
    {
        $term = trim($term);
        $page = max(1, $page);

        if ($user->isSuperUser()) {
            $query = Location::withoutGlobalScopes()->with([
                'parent' => fn ($q) => $q->withoutGlobalScopes(),
                'company' => fn ($q) => $q->withoutGlobalScopes(),
            ])->whereNull('locations.deleted_at')->whereHas('profile');
            if ($term !== '') {
                $query->where('locations.name', 'LIKE', '%'.addcslashes($term, '%_\\').'%');
            }
            $rows = $query->orderBy('locations.name')->skip(($page - 1) * self::PAGE_SIZE)->take(self::PAGE_SIZE + 1)->get();
            $results = $rows->take(self::PAGE_SIZE)->map(fn (Location $location) => $this->target('location_id', $location->id, $location))->all();

            return ['results' => $results, 'more' => $rows->count() > self::PAGE_SIZE];
        }

        $needle = mb_strtolower($term);
        $matches = $this->memberships($user)
            ->filter(fn ($membership) => $needle === '' || str_contains(mb_strtolower($membership->location->name), $needle))
            ->values();
        $results = $matches->slice(($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE)
            ->map(fn ($membership) => $this->target('membership_id', $membership->id, $membership->location))->values()->all();

        return ['results' => $results, 'more' => $matches->count() > $page * self::PAGE_SIZE];
    }

    /** Recently used offices that the user may still switch to, newest first. */
    public function recent(User $user): Collection
    {
        $ids = $this->recentIds((int) $user->id);
        if (! $ids) {
            return collect();
        }
        if ($user->isSuperUser()) {
            $locations = Location::withoutGlobalScopes()->whereIn('id', $ids)->whereNull('deleted_at')->whereHas('profile')->get()->keyBy('id');

            return collect($ids)->map(fn ($id) => $locations->get($id))->filter()
                ->map(fn (Location $location) => $this->target('location_id', $location->id, $location))->values();
        }
        $memberships = $this->memberships($user)->keyBy('location_id');

        return collect($ids)->map(fn ($id) => $memberships->get($id))->filter()
            ->map(fn ($membership) => $this->target('membership_id', $membership->id, $membership->location))->values();
    }

    public function remember(int $userId, int $locationId): void
    {
        $ids = array_values(array_unique(array_merge([$locationId], $this->recentIds($userId))));
        Cache::put($this->key($userId), array_slice($ids, 0, self::RECENT_LIMIT), now()->addDays(90));
    }

    /** @return int[] */
    public function recentIds(int $userId): array
    {
        return array_values(array_filter(array_map('intval', (array) Cache::get($this->key($userId), []))));
    }

    private function key(int $userId): string
    {
        return 'gov-membership:recent-offices:'.$userId;
    }

    private function target(string $field, int $value, Location $location): array
    {
        $sub = array_filter([$location->parent?->name, $location->company?->name]);

        return ['field' => $field, 'value' => $value, 'location_id' => (int) $location->id,
            'text' => (string) $location->name, 'sub' => implode(' · ', $sub)];
    }
}
