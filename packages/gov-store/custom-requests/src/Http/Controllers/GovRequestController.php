<?php

namespace GovStore\CustomRequests\Http\Controllers;

use App\Models\Accessory;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Location;
use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Services\CatalogService;
use GovStore\CustomRequests\Services\PolicyService;
use GovStore\CustomRequests\Services\RequesterService;
use GovStore\CustomRequests\Services\RequestInventory;
use GovStore\CustomRequests\Services\RequestNotices;
use GovStore\CustomRequests\Services\RequestReturnService;
use GovStore\CustomRequests\Support\DraftView;
use GovStore\CustomRequests\Support\RequestPresenter;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Controllers\Concerns\RespondsWithRefusals;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GovRequestController extends Controller
{
    use RespondsWithRefusals;

    private const TABS = ['active', 'needs_you', 'finished'];

    /** The requester's requests in the working office, newest first. */
    private function own()
    {
        return ServiceRequest::query()->where('requested_by', auth()->id())->where('approval_status', '!=', 'draft')
            ->where(fn ($q) => $q->where('office_id', app(TenantContext::class)->locationId)->orWhereNull('office_id'));
    }

    /** My requests (board 5): tabs In progress · Needs you · Finished, a next step on every row, dated updates. */
    public function index(Request $request, RequestPresenter $presenter, RequestNotices $notices)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : null;
        $all = $this->own()->with(['items', 'events'])->orderByDesc('submitted_at')->orderByDesc('id')->get();
        $grouped = $all->groupBy(fn ($r) => $r->tabKey());
        $counts = collect(self::TABS)->mapWithKeys(fn ($key) => [$key => $grouped->get($key, collect())->count()])->all();
        $tab ??= $counts['needs_you'] > 0 && $counts['active'] === 0 ? 'needs_you' : 'active';
        $requests = $grouped->get($tab, collect());
        $presenter->preloadLines($requests);
        $updates = $notices->forUser(auth()->id(), true, 8)->map(fn ($n) => $presenter->notice($n));
        // F8: updates are read once the person has seen them here.
        $notices->markRead(auth()->id());
        $office = Location::find(app(TenantContext::class)->locationId)?->name ?? '';

        return view('govstore::user.index', compact('requests', 'counts', 'tab', 'updates', 'office', 'presenter'));
    }

    /** The request page (board 6, F1): only the requester, only in the request's office; 404 otherwise. */
    public function show($id, RequestPresenter $presenter, RequestNotices $notices)
    {
        $serviceRequest = ServiceRequest::with(['items', 'events', 'siblings'])->where('requested_by', auth()->id())
            ->where('office_id', app(TenantContext::class)->locationId)->findOrFail((int) $id);
        $presenter->preloadLines(collect([$serviceRequest]));
        $notices->markRead(auth()->id(), $serviceRequest->id);

        return view('govstore::user.show', compact('serviceRequest', 'presenter'));
    }

    /**
     * Request items: cards or a list (kept for the session), kind tabs with counts, quick-add shortcuts and the
     * live draft panel. AJAX requests get the result list only, for live search.
     */
    public function catalog(Request $request, CatalogService $catalogService, DraftView $drafts, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $filters = $request->validate(['q' => 'nullable|string|max:200', 'type' => 'nullable|in:asset_model,accessory,consumable',
            'category_id' => 'nullable|integer|min:1', 'free' => 'nullable|boolean', 'page' => 'nullable|integer|min:1',
            'view' => 'nullable|in:grid,list']);
        if (isset($filters['view'])) {
            session(['gov_requests.catalog_view' => $filters['view']]);
        }
        $view = session('gov_requests.catalog_view', 'grid');
        unset($filters['view']);
        $catalogItems = $catalogService->paginate($filters);
        $draft = $drafts->make(DraftBasket::openFor(auth()->id())->first());
        $inDraft = $draft['lines']->keyBy('key');
        $rules = app(PolicyService::class)->explainMany($catalogItems->getCollection()->filter(fn ($item) => $item->available_qty > 0)
            ->map(fn ($item) => (object) ['type' => $item->type, 'id' => $item->id, 'category_id' => $item->category_id,
                'qty' => $inDraft->get($item->type.':'.$item->id)?->qty ?? 1]));
        $counts = $catalogService->counts($filters);
        if ($request->ajax()) {
            return view('govstore::catalog.results', compact('catalogItems', 'inDraft', 'rules', 'filters', 'view', 'counts', 'presenter'));
        }
        $usual = $catalogService->usual(auth()->id(), 6);
        $categories = Category::orderBy('name')->get(['id', 'name']);
        $office = Location::find(app(TenantContext::class)->locationId)?->name ?? '';

        return view('govstore::catalog.index', compact('catalogItems', 'inDraft', 'rules', 'filters', 'view', 'counts', 'draft', 'usual',
            'categories', 'office', 'presenter'));
    }

    public function withdraw($id, RequesterService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        return $this->attempt(fn () => $service->transition((int) $id, auth()->user(), 'withdraw'),
            fn () => $presenter->t('request_withdrawn', ['number' => ServiceRequest::find((int) $id)?->request_number]));
    }

    public function receive($id, RequesterService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        return $this->attempt(fn () => $service->transition((int) $id, auth()->user(), 'receive'),
            fn () => $presenter->t('receipt_recorded', ['number' => ServiceRequest::find((int) $id)?->request_number]));
    }

    public function requestReturn(Request $request, $id, RequestReturnService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $request->validate(['reason' => 'required|string|min:5|max:2000']);

        return $this->attempt(fn () => $service->requestReturn((int) $id, auth()->user(), $request->input('reason')),
            $presenter->t('return_requested_flash'));
    }

    /**
     * Alternatives for Issue items, from the working office's store: free count, price, and whether the
     * price rule allows it when `for_line` names the approved line (category and cost no higher).
     */
    public function search(Request $request, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $request->validate(['q' => 'nullable|string|max:200', 'type' => 'required|in:asset_model,consumable,accessory', 'for_line' => 'nullable|integer|min:1']);
        $term = trim((string) $request->input('q', ''));
        $type = $request->input('type');
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $office = app(TenantContext::class)->locationId;
        abort_unless($office, 422);
        $inventory = app(RequestInventory::class);
        $rule = null;
        if ($request->filled('for_line')) {
            // The approved line must belong to an open request of this office; others are not found.
            $line = RequestItem::whereKey((int) $request->input('for_line'))
                ->whereHas('request', fn ($q) => $q->where('office_id', $office))->firstOrFail();
            $facts = $presenter->itemFacts($line->requested_type, $line->requested_id);
            $rule = ['id' => (int) $line->requested_id, 'category' => $facts['category_id'], 'cost' => $facts['cost'],
                'needed' => max(0, $line->approved_qty - $line->issued_qty)];
        }
        $items = match ($type) {
            'consumable' => $inventory->scopeCompany(Consumable::where('location_id', $office))->where('name', 'like', "%{$term}%")->limit(15)->get(),
            'accessory' => $inventory->scopeCompany(Accessory::where('location_id', $office))->where('name', 'like', "%{$term}%")->limit(15)->get(),
            default => AssetModel::where('name', 'like', "%{$term}%")
                ->whereHas('assets', fn ($q) => $inventory->scopeCompany($q, 'assets.company_id')->where('location_id', $office)->whereNull('assigned_to')->where('requestable', 1)
                    ->whereHas('status', fn ($q) => $q->where('deployable', 1)->where('archived', 0)))->limit(15)->get(),
        };

        return response()->json($items->reject(fn ($item) => $rule && (int) $item->id === $rule['id'])->map(function ($item) use ($inventory, $type, $office, $rule, $presenter) {
            $free = $inventory->available($type, $item, $office);
            $cost = $type === 'asset_model' ? null : $item->purchase_cost;
            $why = null;
            if ($rule) {
                $why = match (true) {
                    ! $rule['category'] || (int) $item->category_id !== (int) $rule['category'] => 'alt_other_category',
                    $rule['cost'] === null || $cost === null => 'alt_no_price',
                    (float) $cost > (float) $rule['cost'] => 'alt_dearer',
                    $free < $rule['needed'] => 'alt_too_few',
                    default => null,
                };
            }

            return ['id' => $item->id, 'text' => $item->name, 'name' => $item->name, 'free' => $free,
                'free_label' => $presenter->t('alt_free', ['count' => $free]),
                'price' => $cost, 'price_label' => $cost !== null ? $presenter->t('alt_price', ['price' => number_format((float) $cost, 2)]) : $presenter->t('alt_no_price'),
                'allowed' => $rule ? $why === null : true, 'note' => $why ? $presenter->t($why) : null];
        })->values());
    }
}
