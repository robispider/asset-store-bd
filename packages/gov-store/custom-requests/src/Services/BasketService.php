<?php

namespace GovStore\CustomRequests\Services;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use GovStore\CustomRequests\Exceptions\RequestRefused;
use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BasketService
{
    public function getOrCreateDraftBasket(int $userId): DraftBasket
    {
        return DraftBasket::getOrCreateForUser($userId);
    }

    public function addItem(int $userId, string $itemType, int $itemId, int $qty = 1): DraftBasket
    {
        $itemType = strtolower(class_basename($itemType));
        if ($itemType === 'asset') {
            $asset = Asset::findOrFail($itemId);
            abort_unless((int) $asset->location_id === app(TenantContext::class)->locationId, 404);
            $itemType = 'asset_model';
            $itemId = $asset->model_id;
        }
        if ($itemType === 'assetmodel') {
            $itemType = 'asset_model';
        }
        $inventory = app(RequestInventory::class);
        $model = $inventory->validateItem($itemType, $itemId);
        $this->validateQuantity($qty);
        // D1: items with nothing free are listed in the catalogue but cannot be added.
        RequestRefused::when($inventory->available($itemType, $model, app(TenantContext::class)->locationId) < 1, 409,
            'request_refused_no_free_stock', 'item', ['item' => $model->name]);

        return DB::transaction(function () use ($userId, $itemType, $itemId, $qty) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $basket = $this->getOrCreateDraftBasket($userId);
            $item = $basket->items()->where('requested_type', $itemType)->where('requested_id', $itemId)->first();
            $quantity = ($item ? $item->requested_qty : 0) + $qty;
            $this->validateQuantity($quantity);
            $basket->items()->updateOrCreate(['requested_type' => $itemType, 'requested_id' => $itemId], ['requested_qty' => $quantity]);

            return $basket;
        });
    }

    public function updateItemQty(int $userId, int $itemId, int $qty): DraftBasket
    {
        $this->validateQuantity($qty);

        return $this->editBasket($userId, fn ($basket) => $basket->items()->whereKey($itemId)->firstOrFail()->update(['requested_qty' => $qty]));
    }

    public function removeItem(int $userId, int $itemId): DraftBasket
    {
        return $this->editBasket($userId, fn ($basket) => $basket->items()->whereKey($itemId)->firstOrFail()->delete());
    }

    private function editBasket(int $userId, callable $callback): DraftBasket
    {
        return DB::transaction(function () use ($userId, $callback) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $basket = DraftBasket::openFor($userId)->lockForUpdate()->firstOrFail();
            $this->claim($basket);
            $callback($basket);

            return $basket;
        });
    }

    /** A draft saved before drafts had an office belongs to the office it is next changed or sent in. */
    private function claim(DraftBasket $basket): void
    {
        $office = app(TenantContext::class)->locationId;
        if ($basket->office_id === null && $office) {
            $basket->update(['office_id' => $office]);
        }
    }

    /**
     * What the person reviewed, line by line: the item, the quantity, the approval rule it will follow and
     * whether it asks for more than is free. Sending compares this with what they saw on the review step,
     * so a draft changed in another tab, a rule that now applies, or stock that ran short since is shown
     * to them instead of being sent unseen. Free counts that move without crossing the asked quantity
     * do not count as a change.
     *
     * @return array<int, array{item:string, qty:int, policy:string, short:bool}>
     */
    public function snapshot(DraftBasket $basket): array
    {
        $office = app(TenantContext::class)->locationId;
        $inventory = app(RequestInventory::class);

        return $basket->items()->orderBy('requested_type')->orderBy('requested_id')->get()->map(function ($item) use ($inventory, $office) {
            try {
                $model = $inventory->validateItem($item->requested_type, (int) $item->requested_id);
                $free = $inventory->available($item->requested_type, $model, $office);
                $policy = app(PolicyService::class)->resolvePolicy($item->requested_type, (int) $item->requested_id, (int) $item->requested_qty);
            } catch (HttpExceptionInterface|ModelNotFoundException) {
                [$free, $policy] = [0, 'UNAVAILABLE'];
            }

            return ['item' => $item->requested_type.':'.$item->requested_id, 'qty' => (int) $item->requested_qty,
                'policy' => $policy, 'short' => (int) $item->requested_qty > $free];
        })->values()->all();
    }

    /**
     * How the draft differs from what was reviewed: one entry per changed line.
     *
     * @return array<int, array{item:string, change:string, qty:int, before:?int, policy:?string}>
     */
    public function changes(array $reviewed, array $current): array
    {
        $before = collect($this->normalise($reviewed))->keyBy('item');
        $now = collect($current)->keyBy('item');
        $changes = [];
        foreach ($now as $key => $line) {
            $old = $before->get($key);
            if (! $old) {
                $changes[] = ['item' => $key, 'change' => 'added', 'qty' => $line['qty'], 'before' => null, 'policy' => $line['policy']];
            } elseif ($old['qty'] !== $line['qty']) {
                $changes[] = ['item' => $key, 'change' => 'qty', 'qty' => $line['qty'], 'before' => $old['qty'], 'policy' => $line['policy']];
            } elseif ($old['policy'] !== $line['policy']) {
                $changes[] = ['item' => $key, 'change' => $line['policy'] === 'UNAVAILABLE' ? 'gone' : 'rule', 'qty' => $line['qty'], 'before' => null, 'policy' => $line['policy']];
            } elseif ($line['short'] && ! $old['short']) {
                $changes[] = ['item' => $key, 'change' => 'short', 'qty' => $line['qty'], 'before' => null, 'policy' => $line['policy']];
            }
        }
        foreach ($before->keys()->diff($now->keys()) as $key) {
            $changes[] = ['item' => $key, 'change' => 'removed', 'qty' => $before[$key]['qty'], 'before' => null, 'policy' => null];
        }

        return $changes;
    }

    /** Only well-formed lines from the page count. */
    private function normalise(array $lines): array
    {
        return collect($lines)->filter(fn ($line) => is_array($line) && is_string($line['item'] ?? null) && is_numeric($line['qty'] ?? null))
            ->map(fn ($line) => ['item' => $line['item'], 'qty' => (int) $line['qty'], 'policy' => (string) ($line['policy'] ?? ''), 'short' => (bool) ($line['short'] ?? false)])
            ->values()->all();
    }

    private function validateQuantity(int $qty): void
    {
        Validator::make(['qty' => $qty], ['qty' => 'required|integer|min:1|max:'.RequestWorkflow::MAX_QUANTITY])->validate();
    }

    /**
     * How the draft will be sent (board 4): one group per approval rule, each with its lines and the rule's
     * reasons, so the page can say "This will be sent as 2 requests" before anything is sent.
     *
     * @return array<string, array{policy:string, lines:\Illuminate\Support\Collection, reasons:array}>
     */
    public function preview(DraftBasket $basket): array
    {
        $groups = [];
        foreach ($basket->items as $item) {
            try {
                $reason = app(PolicyService::class)->explain($item->requested_type, $item->requested_id, (int) $item->requested_qty);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException) {
                continue; // Unavailable items are shown as such and refused on send.
            }
            $groups[$reason['policy']] ??= ['policy' => $reason['policy'], 'lines' => collect(), 'reasons' => []];
            $groups[$reason['policy']]['lines']->push($item);
            $groups[$reason['policy']]['reasons'][$item->id] = $reason;
        }
        uksort($groups, fn ($a, $b) => array_search($a, RequestWorkflow::POLICIES) <=> array_search($b, RequestWorkflow::POLICIES));
        $office = (int) ($basket->office_id ?: app(TenantContext::class)->locationId);
        foreach ($groups as $policy => &$group) {
            $group['approval_available'] = app(ApprovalRouting::class)->feasible($office, $policy, [(int) $basket->user_id]);
        }
        unset($group);

        return $groups;
    }

    /**
     * Send the draft. $reviewed is the snapshot() the person saw on the review step: when it no longer
     * matches, nothing is sent (409, field "reviewed") and the page shows what changed. $metadata may
     * carry "declared" (the requester confirmed the declaration), recorded with the time in the history.
     */
    public function submitBasket(int $userId, array $metadata, ?array $reviewed = null): array
    {
        $declared = (bool) ($metadata['declared'] ?? false);
        unset($metadata['declared']);
        $metadata = Validator::make($metadata, [
            'request_type' => ['required', Rule::in(RequestWorkflow::REQUEST_TYPES)],
            'purpose' => 'required|string|max:255', 'justification' => 'nullable|string|max:10000',
            'required_by_date' => 'nullable|date|after_or_equal:today', 'cost_center' => 'nullable|string|max:50',
            'delivery_location_id' => 'nullable|integer|exists:locations,id',
        ])->validate();
        $context = app(TenantContext::class);
        $requester = User::findOrFail($userId);
        abort_unless(app(GovAccess::class)->permitsRequest($requester, 'requests.submit'), 403);
        RequestRefused::unless($context->locationId && Location::whereKey($context->locationId)->exists(), 422, 'request_refused_no_office');
        if (! empty($metadata['delivery_location_id'])) {
            $allowed = $context->allowedLocationIds ?? [$context->locationId];
            abort_unless($requester->isSuperUser() || in_array((int) $metadata['delivery_location_id'], $allowed), 404);
            Location::findOrFail($metadata['delivery_location_id']);
        }

        return DB::transaction(function () use ($requester, $metadata, $context, $reviewed, $declared) {
            app(\GovStore\Organization\Services\OfficeRequestIntake::class)->assertOpen([
                $context->locationId, (int) ($metadata['delivery_location_id'] ?? $context->locationId),
            ], true);
            User::whereKey($requester->id)->lockForUpdate()->firstOrFail();
            $basket = DraftBasket::openFor($requester->id)->lockForUpdate()->first();
            RequestRefused::when(! $basket, 422, 'request_refused_empty_draft', 'items');
            $this->claim($basket);
            $items = $basket->items()->orderBy('requested_type')->orderBy('requested_id')->get();
            RequestRefused::when($items->isEmpty(), 422, 'request_refused_empty_draft', 'items');
            if ($reviewed !== null) {
                RequestRefused::when($this->changes($reviewed, $this->snapshot($basket)) !== [], 409, 'request_refused_draft_changed', 'reviewed');
            }
            $groups = [];
            $reasons = [];
            foreach ($items as $item) {
                $this->validateQuantity($item->requested_qty);
                app(RequestInventory::class)->validateItem($item->requested_type, $item->requested_id);
                $reason = app(PolicyService::class)->explain($item->requested_type, $item->requested_id, $item->requested_qty);
                $groups[$reason['policy']][] = $item;
                $reasons[$reason['policy']][] = $reason;
            }
            // The reason is for the approver: asked for whenever someone has to approve, optional otherwise.
            RequestRefused::when(blank($metadata['justification'] ?? null) && array_diff(array_keys($groups), ['AUTO_APPROVE']) !== [],
                422, 'request_refused_reason_needed', 'justification');
            $metadata['justification'] = (string) ($metadata['justification'] ?? '');
            // D2: requests sent together share a batch, so each request page can name the others and the
            // Sent page lists exactly this submission.
            $batch = (string) Str::uuid();
            $submitted = [];
            foreach ($groups as $policy => $lines) {
                $automatic = $policy === 'AUTO_APPROVE';
                $request = ServiceRequest::create($metadata + [
                    'office_id' => $context->locationId, 'requested_by' => $requester->id,
                    'resolved_policy' => $policy, 'approval_status' => $automatic ? 'approved' : 'pending_primary',
                    'fulfillment_status' => 'unstarted', 'submitted_at' => now(), 'approved_at' => $automatic ? now() : null,
                    'submission_batch' => $batch, 'resolved_reason' => ['lines' => $reasons[$policy]],
                ]);
                foreach ($lines as $line) {
                    $request->items()->create([
                        'requested_type' => $line->requested_type, 'requested_id' => $line->requested_id,
                        'requested_qty' => $line->requested_qty, 'approved_qty' => $automatic ? $line->requested_qty : 0,
                        'line_approval_status' => $automatic ? 'approved' : 'pending',
                        'line_fulfillment_status' => $automatic ? 'waiting' : 'unstarted',
                    ]);
                }
                if ($automatic) {
                    app(RequestInventory::class)->reserve($request);
                } else {
                    app(ApprovalRouting::class)->assign($request);
                }
                RequestEvent::create(['request_id' => $request->id, 'user_id' => $requester->id,
                    'event_type' => $automatic ? 'auto_approved' : 'submitted', 'details' => ['policy' => $policy, 'office_id' => $context->locationId,
                        'stage' => $request->approval_status, 'routing_reason' => $request->assigned_approver_id ? 'independent_approver' : 'office_admin_cover_required']
                    + ($declared ? ['declared_at' => now()->toIso8601String(), 'declaration' => 'official_use_v1'] : [])]);
                $submitted[] = $request;
            }
            $basket->items()->delete();
            $basket->delete();

            return $submitted;
        }, 3);
    }
}
