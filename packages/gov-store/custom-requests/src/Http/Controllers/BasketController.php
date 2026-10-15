<?php

namespace GovStore\CustomRequests\Http\Controllers;

use App\Models\Location;
use GovStore\CustomRequests\Exceptions\RequestRefused;
use GovStore\CustomRequests\Models\BasketItem;
use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\BasketService;
use GovStore\CustomRequests\Services\CatalogService;
use GovStore\CustomRequests\Services\PolicyService;
use GovStore\CustomRequests\Support\DraftView;
use GovStore\CustomRequests\Support\RequestPresenter;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Controllers\Concerns\RespondsWithRefusals;
use GovStore\TenantScope\Services\ActionFailure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class BasketController extends Controller
{
    use RespondsWithRefusals;

    /** Fields of the details step; errors on these open the checkout at step 2. */
    public const DETAIL_FIELDS = ['request_type', 'purpose', 'justification', 'needed', 'required_by_date', 'delivery_location_id', 'cost_center'];

    /** Checkout: 1 Items · 2 Details · 3 Review and send, on one page. */
    public function index(BasketService $service, DraftView $drafts, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $draft = $drafts->make($service->getOrCreateDraftBasket(auth()->id()));
        $office = $draft['office'];
        $locations = Location::whereIn('id', app(TenantContext::class)->allowedLocationIds ?? [$office])->orderBy('name')->get();
        $officeName = Location::find($office)?->name ?? '';
        // One-time token: a second press, a slow network or Back-and-resend cannot send this draft twice.
        $submitToken = (string) Str::uuid();

        return view('govstore::basket.index', compact('draft', 'locations', 'presenter', 'office', 'officeName', 'submitToken'));
    }

    public function add(Request $request, BasketService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $request->validate([
            'item_type' => 'required|string',
            'item_id' => 'required|integer',
            'qty' => 'nullable|integer|min:1|max:10000',
        ]);

        try {
            // Normalize PascalCase (e.g. 'Consumable') to standard lowercase morph key ('consumable')
            $normalizedType = strtolower(class_basename($request->item_type));
            $basket = $service->addItem(auth()->id(), $normalizedType, $request->item_id, (int) $request->input('qty', 1));
            $line = $basket->items()->latest('updated_at')->first();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $presenter->t('item_added_named', ['item' => $line ? $presenter->itemName($line->requested_type, (int) $line->requested_id) : '']),
                    'count' => $basket->items()->count(),
                    'line' => $line ? ['id' => $line->id, 'qty' => (int) $line->requested_qty] : null,
                    'in_draft' => $line ? $presenter->t('catalog_in_draft', ['count' => (int) $line->requested_qty]) : null,
                ] + $this->fragments($request, $basket, $line ? $line->requested_type.':'.$line->requested_id : null));
            }

            return redirect()->back()->with('success', $presenter->t('item_added'));
        } catch (RequestRefused $refusal) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $refusal->getMessage()], $refusal->getStatusCode());
            }

            return redirect()->back()->withErrors(['item' => $refusal->getMessage()]);
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException || $e instanceof HttpExceptionInterface || $e instanceof ModelNotFoundException) {
                throw $e;
            }
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => app(ActionFailure::class)->message($e)], 400);
            }

            return redirect()->back()->with('error', app(ActionFailure::class)->message($e));
        }
    }

    /**
     * Update the quantity of an item in the basket.
     * Supports both standard redirect and background AJAX auto-saves.
     */
    public function updateQty(Request $request, BasketService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $request->validate(['item_id' => 'required|integer|min:1', 'qty' => 'required|integer|min:1|max:10000']);
        $key = $this->lineKey((int) $request->item_id);
        $basket = $service->updateItemQty(auth()->id(), $request->item_id, (int) $request->qty);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $presenter->t('qty_saved'), 'count' => $basket->items()->count()]
                + $this->fragments($request, $basket, $key));
        }

        return redirect()->back()->with('success', $presenter->t('qty_saved'));
    }

    public function remove(Request $request, $itemId, BasketService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $key = $this->lineKey((int) $itemId);
        $basket = $service->removeItem(auth()->id(), (int) $itemId);
        if ($request->ajax() || $request->wantsJson()) {
            [$type, $id] = array_pad(explode(':', (string) $key, 2), 2, 0);

            return response()->json(['success' => true, 'count' => $basket->items()->count(),
                'message' => $presenter->t('item_removed_named', ['item' => $presenter->itemName($type, (int) $id)])]
                + $this->fragments($request, $basket, $key));
        }

        return redirect()->back()->with('success', $presenter->t('item_removed'));
    }

    public function submit(Request $request, BasketService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        // "Within a week" is a date the store can plan for; "No rush" sends no date.
        $needed = $request->input('needed');
        if ($needed === 'week') {
            $request->merge(['required_by_date' => now()->addWeek()->toDateString()]);
        } elseif ($needed === 'none') {
            $request->merge(['required_by_date' => null]);
        }
        $request->validate([
            'request_type' => 'required|in:'.implode(',', RequestWorkflow::REQUEST_TYPES),
            'purpose' => 'required|string|max:255',
            'justification' => 'nullable|string|max:10000',
            'needed' => 'nullable|in:none,week,date',
            'required_by_date' => ($needed === 'date' ? 'required' : 'nullable').'|date|after_or_equal:today',
            'delivery_location_id' => 'nullable|integer|exists:locations,id',
            'cost_center' => 'nullable|string|max:50',
            'declared' => 'accepted',
            'submit_token' => 'nullable|uuid',
            'reviewed' => 'nullable|string|max:65000',
        ], [
            'declared.accepted' => $presenter->t('declare_required'),
            'required_by_date.required' => $presenter->t('needed_date_required'),
        ]);

        $reviewed = $request->filled('reviewed') ? json_decode((string) $request->input('reviewed'), true) : null;
        $reviewed = is_array($reviewed) ? $reviewed : null;
        $lock = $request->filled('submit_token') ? 'gov-requests:send:'.auth()->id().':'.$request->input('submit_token') : null;
        if ($lock && ! Cache::add($lock, 'sending', now()->addMinutes(10))) {
            // The same draft was already sent (double press, Back-and-resend): show that submission again.
            $batch = Cache::get($lock);
            if ($batch && $batch !== 'sending') {
                return redirect()->route('gov.requests.sent', $batch)->with('info', $presenter->t('already_sent'));
            }

            return back()->withErrors(['request' => $presenter->t('request_refused_sending')]);
        }

        try {
            $requests = $service->submitBasket(auth()->id(), $request->only(['request_type', 'purpose', 'justification',
                'required_by_date', 'delivery_location_id', 'cost_center']) + ['declared' => true], $reviewed);
        } catch (RequestRefused $refusal) {
            $lock && Cache::forget($lock);
            if ($request->expectsJson()) {
                throw $refusal;
            }
            $back = back()->withErrors([$refusal->field => $refusal->getMessage()])->withInput($request->except(['declared', 'submit_token', 'reviewed']));
            if ($refusal->field === 'reviewed' && $reviewed !== null) {
                // Say what changed since the review, so the person can check it before confirming again.
                $basket = DraftBasket::openFor(auth()->id())->first();
                $changes = $basket ? $service->changes($reviewed, $service->snapshot($basket)) : [];
                $back->with('cr_changes', array_map(fn ($change) => $presenter->changeSentence($change), $changes));
            }

            return $back;
        } catch (\Throwable $e) {
            $lock && Cache::forget($lock);
            // Office intake paused and similar: other packages' 409s already carry a translated message.
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 409 && $e->getMessage() !== '' && ! $request->expectsJson()) {
                return back()->withErrors(['request' => $e->getMessage()])->withInput($request->except(['declared', 'submit_token', 'reviewed']));
            }
            throw $e;
        }

        $batch = $requests[0]->submission_batch;
        $lock && Cache::put($lock, $batch, now()->addHour());

        return redirect()->route('gov.requests.sent', $batch);
    }

    /** Sent: the requests from one submission, who has each now and what happens next. Owner and office only. */
    public function sent(string $batch, RequestPresenter $presenter)
    {
        $requests = ServiceRequest::with(['items', 'events'])->where('submission_batch', $batch)
            ->where('requested_by', auth()->id())->where('office_id', app(TenantContext::class)->locationId)
            ->orderBy('id')->get();
        abort_if($requests->isEmpty(), 404);
        $presenter->preloadLines($requests);

        return view('govstore::basket.sent', compact('requests', 'presenter'));
    }

    /** "Request these again" / "Ask again": the request's items go into the draft, which then opens. */
    public function again($id, BasketService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $source = ServiceRequest::with('items')->where('requested_by', auth()->id())
            ->where('office_id', app(TenantContext::class)->locationId)->findOrFail((int) $id);
        $failed = 0;
        foreach ($source->items as $line) {
            try {
                $service->addItem(auth()->id(), $line->requested_type, (int) $line->requested_id, max(1, (int) $line->requested_qty));
            } catch (HttpExceptionInterface|ModelNotFoundException|ValidationException) {
                $failed++;
            }
        }

        return redirect()->route('gov.requests.basket.index')->with('success', $failed
            ? $presenter->t('added_again_some', ['number' => $source->request_number, 'count' => $failed])
            : $presenter->t('added_again', ['number' => $source->request_number]));
    }

    /** "type:id" of one of the person's own draft lines (null when it is not theirs; the service then refuses). */
    private function lineKey(int $lineId): ?string
    {
        $line = BasketItem::whereKey($lineId)->whereHas('basket', fn ($q) => $q->where('user_id', auth()->id()))->first();

        return $line ? $line->requested_type.':'.$line->requested_id : null;
    }

    /**
     * Fresh HTML for the parts of the page a draft change touches, so the page never shows a stale draft.
     * context=catalog: the draft panel and the changed item's card. context=checkout: the lines, the
     * summary, the review groups, the snapshot to send and whether a reason is needed.
     */
    private function fragments(Request $request, DraftBasket $basket, ?string $key): array
    {
        $context = $request->input('context');
        if (! in_array($context, ['catalog', 'checkout'], true)) {
            return [];
        }
        $presenter = app(RequestPresenter::class);
        $draft = app(DraftView::class)->make($basket->fresh());
        $fragments = [];
        if ($context === 'catalog') {
            $fragments['drawer'] = view('govstore::basket.partials.drawer', compact('draft', 'presenter'))->render();
            $item = $key ? app(CatalogService::class)->find($key) : null;
            if ($item) {
                $inDraft = $draft['lines']->keyBy('key');
                $rules = app(PolicyService::class)->explainMany([(object) ['type' => $item->type, 'id' => $item->id,
                    'category_id' => $item->category_id, 'qty' => $inDraft->get($key)?->qty ?? 1]]);
                $fragments['item'] = view('govstore::catalog.partials.item', compact('item', 'inDraft', 'rules', 'presenter'))->render();
                $fragments['item_id'] = 'cr-item-'.$item->type.'-'.$item->id;
            }
        } else {
            $fragments['lines'] = view('govstore::basket.partials.lines', compact('draft', 'presenter'))->render();
            $fragments['summary'] = view('govstore::basket.partials.summary', compact('draft', 'presenter'))->render();
            $fragments['groups'] = view('govstore::basket.partials.groups', compact('draft', 'presenter'))->render();
        }

        return ['fragments' => $fragments, 'reviewed' => json_encode($draft['snapshot']), 'needs_reason' => $draft['needsReason'],
            'reader' => $draft['reader'], 'empty' => $draft['count'] === 0,
            'send_label' => $presenter->choice('draft_send', max(1, count($draft['groups'])), ['count' => count($draft['groups'])])];
    }
}
