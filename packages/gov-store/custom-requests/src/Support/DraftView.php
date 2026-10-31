<?php

namespace GovStore\CustomRequests\Support;

use App\Models\Location;
use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Services\BasketService;
use GovStore\CustomRequests\Services\RequestInventory;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Everything the draft panel (Request items) and the checkout show about a draft: each line with its
 * free stock and approval rule, how the draft will be split, whether a reason is needed and the
 * snapshot the review step posts back. Built once per page or per AJAX change.
 */
class DraftView
{
    public function __construct(private BasketService $service, private RequestPresenter $presenter) {}

    /**
     * @return array{basket: ?DraftBasket, lines: \Illuminate\Support\Collection, groups: array, needsReason: bool,
     *     reader: ?string, snapshot: array, office: int, officeName: string, requester: int, count: int}
     */
    public function make(?DraftBasket $basket): array
    {
        $office = (int) app(TenantContext::class)->locationId;
        $requester = (int) auth()->id();
        $officeName = Location::find($office)?->name ?? '';
        $empty = ['basket' => $basket, 'lines' => collect(), 'groups' => [], 'needsReason' => false, 'reader' => null,
            'snapshot' => [], 'office' => $office, 'officeName' => $officeName, 'requester' => $requester, 'count' => 0];
        if (! $basket) {
            return $empty;
        }
        $basket->load('items');
        if ($basket->items->isEmpty()) {
            return $empty;
        }
        $this->presenter->preloadItems($basket->items->map(fn ($line) => [$line->requested_type, $line->requested_id]));
        $groups = $this->service->preview($basket);
        $rules = [];
        foreach ($groups as $group) {
            foreach ($group['lines'] as $line) {
                $rules[$line->id] = [$group['policy'], $group['reasons'][$line->id] ?? null, $group['submission']];
            }
        }
        $inventory = app(RequestInventory::class);
        $lines = $basket->items->map(function ($line) use ($inventory, $office, $rules) {
            try {
                $free = $inventory->available($line->requested_type, $inventory->validateItem($line->requested_type, (int) $line->requested_id), $office);
            } catch (HttpExceptionInterface|ModelNotFoundException) {
                $free = null; // No longer in this office's catalogue: shown, and refused on send.
            }
            [$policy, $reason, $submission] = $rules[$line->id] ?? [null, null, null];

            return (object) [
                'id' => (int) $line->id, 'type' => $line->requested_type, 'item_id' => (int) $line->requested_id,
                'key' => $line->requested_type.':'.$line->requested_id, 'qty' => (int) $line->requested_qty,
                'name' => $this->presenter->itemName($line->requested_type, (int) $line->requested_id),
                'type_label' => $this->presenter->typeLabel($line->requested_type),
                'free' => $free, 'policy' => $policy, 'reason' => $reason, 'submission' => $submission,
                'short' => $free !== null && (int) $line->requested_qty > $free,
            ];
        });
        // The same lines BasketService::snapshot() rebuilds when the draft is sent.
        $snapshot = $lines->sortBy([['type', 'asc'], ['item_id', 'asc']])->map(fn ($line) => [
            'item' => $line->key, 'qty' => $line->qty, 'policy' => $line->policy ?? 'UNAVAILABLE', 'short' => $line->qty > ($line->free ?? 0),
            'submission' => $line->submission,
        ])->values()->all();
        $policies = array_keys($groups);

        return ['basket' => $basket, 'lines' => $lines, 'groups' => $groups,
            'needsReason' => collect($groups)->contains(fn ($group) => $group['submission']['status'] !== 'approved'),
            'reader' => $this->presenter->firstReader($policies, $office, $requester), 'snapshot' => $snapshot,
            'office' => $office, 'officeName' => $officeName, 'requester' => $requester, 'count' => $lines->count()];
    }
}
