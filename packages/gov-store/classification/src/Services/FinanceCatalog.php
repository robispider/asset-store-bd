<?php

namespace GovStore\Classification\Services;

use GovStore\Classification\Models\FinanceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The national Finance item list. Codes are never deleted, retired codes are never reused, and the
 * item number II runs across the whole group (D-08): a group holds at most 99 items.
 */
class FinanceCatalog
{
    /** Capacity per group: used item numbers, highest number, labels and tone. */
    public function capacity(): Collection
    {
        return FinanceItem::query()->orderBy('gov_code')->get(['gov_code', 'group_name'])
            ->groupBy(fn ($item) => substr($item->gov_code, 0, 2))
            ->map(function (Collection $items, string $group) {
                $highest = (int) $items->max(fn ($item) => (int) substr($item->gov_code, 4, 2));

                return ['group' => $group, 'name' => $items->first()->group_name, 'used' => $highest,
                    'items' => $items->count(), 'capacity' => FinanceItem::GROUP_CAPACITY,
                    'tone' => $highest >= FinanceItem::GROUP_CAPACITY ? 'danger' : ($highest >= FinanceItem::CAPACITY_WARNING ? 'warning' : 'success')];
            })->values();
    }

    /** Next item number for a group (preview only, nothing is reserved). Null when the group is full. */
    public function nextItemNumber(string $group): ?string
    {
        $highest = (int) FinanceItem::where('gov_code', 'like', $group.'%')->get(['gov_code'])
            ->max(fn ($item) => (int) substr($item->gov_code, 4, 2));

        return $highest >= FinanceItem::GROUP_CAPACITY ? null : str_pad((string) ($highest + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Create a held item in an existing (or new) group/subgroup. The number is allocated under a lock on
     * the group's rows; the group is refused at 99 until the Finance Division decides D-08.
     */
    public function create(array $data, int $actorId): FinanceItem
    {
        $group = (string) $data['group_code'];
        $subgroup = (string) $data['subgroup_code'];

        return DB::transaction(function () use ($data, $group, $subgroup, $actorId) {
            $rows = FinanceItem::where('gov_code', 'like', $group.'%')->lockForUpdate()->get();
            $highest = (int) $rows->max(fn ($item) => (int) substr($item->gov_code, 4, 2));
            if ($highest >= FinanceItem::GROUP_CAPACITY) {
                throw ValidationException::withMessages(['group_code' => __('classification::finance.capacity_full', ['group' => $group])]);
            }
            $groupName = $rows->first()?->group_name ?? trim((string) ($data['group_name'] ?? ''));
            $subgroupName = $rows->first(fn ($item) => substr($item->gov_code, 2, 2) === $subgroup)?->subgroup_name
                ?? trim((string) ($data['subgroup_name'] ?? ''));
            if ($groupName === '' || $subgroupName === '') {
                throw ValidationException::withMessages(['subgroup_name' => __('classification::finance.names_required')]);
            }
            $code = $group.$subgroup.str_pad((string) ($highest + 1), 2, '0', STR_PAD_LEFT);

            return FinanceItem::create(['gov_code' => $code, 'group_name' => $groupName, 'subgroup_name' => $subgroupName,
                'item_name' => trim($data['item_name']), 'unspsc_code' => $data['unspsc_code'] ?? null,
                'status' => 'held', 'origin' => 'publisher', 'authority_reference' => $data['authority_reference'] ?? null,
                'updated_by' => $actorId]);
        });
    }

    /** Activate held items. Items with an open source conflict stay held (D-02). */
    public function activate(array $codes, string $authorityReference, int $actorId): int
    {
        return DB::transaction(function () use ($codes, $authorityReference, $actorId) {
            $count = 0;
            foreach (FinanceItem::whereIn('gov_code', $codes)->where('status', 'held')->lockForUpdate()->get() as $item) {
                if ($this->hasOpenIssues($item)) {
                    continue;
                }
                $item->update(['status' => 'active', 'authority_reference' => $authorityReference, 'updated_by' => $actorId]);
                $count++;
            }

            return $count;
        });
    }

    public function retire(array $codes, int $actorId): int
    {
        return FinanceItem::whereIn('gov_code', $codes)->where('status', 'active')->update(['status' => 'retired', 'updated_by' => $actorId, 'updated_at' => now()]);
    }

    /** Link (or unlink) one UNSPSC commodity; it must exist in the catalogue when set. */
    public function linkUnspsc(string $code, ?string $unspsc, int $actorId): FinanceItem
    {
        $item = FinanceItem::findOrFail($code);
        if ($unspsc !== null && $unspsc !== '' && ! DB::table('gov_catalog_nodes')->where('code', $unspsc)->exists()) {
            throw ValidationException::withMessages(['unspsc_code' => __('classification::finance.unspsc_unknown')]);
        }
        $item->update(['unspsc_code' => $unspsc ?: null, 'updated_by' => $actorId]);

        return $item;
    }

    public function hasOpenIssues(FinanceItem $item): bool
    {
        $source = json_decode((string) $item->source_reference, true);

        return ! empty($source['issues'] ?? []);
    }

    public function counts(): array
    {
        $counts = FinanceItem::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['held' => (int) ($counts['held'] ?? 0), 'active' => (int) ($counts['active'] ?? 0), 'retired' => (int) ($counts['retired'] ?? 0),
            'linked' => FinanceItem::whereNotNull('unspsc_code')->count()];
    }
}
