<?php

namespace GovStore\CustomRequests\Http\Controllers;

use App\Models\Asset;
use App\Models\Location;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\FulfillmentService;
use GovStore\CustomRequests\Services\RequestInventory;
use GovStore\CustomRequests\Services\RequestQueues;
use GovStore\CustomRequests\Support\RequestPresenter;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Controllers\Concerns\RespondsWithRefusals;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class GovFulfillmentController extends Controller
{
    use RespondsWithRefusals;

    private const TABS = ['ready', 'partly', 'returns'];

    private function checkStorekeeperAccess()
    {
        abort_unless(app(GovAccess::class)->permitsRequest(auth()->user(), 'requests.fulfill'), 403);
    }

    private function search(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        return $term === '' ? $query : $query->where(fn ($q) => $q->where('request_number', 'like', "%{$term}%")
            ->orWhere('purpose', 'like', "%{$term}%")
            ->orWhereHas('requester', fn ($u) => $u->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")));
    }

    /** To issue (board 9): Ready · Partly issued · Returns to receive, needed-soonest first, then oldest. */
    public function index(Request $request, RequestQueues $queues, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkStorekeeperAccess();
        $request->validate(['q' => 'nullable|string|max:100']);
        $user = auth()->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'ready';
        $tabs = [
            'ready' => fn () => $queues->awaitingFulfilment($user)->where('fulfillment_status', 'unstarted'),
            'partly' => fn () => $queues->awaitingFulfilment($user)->where('fulfillment_status', 'partially_issued'),
            'returns' => fn () => $queues->returnsToReceive($user),
        ];
        $counts = collect($tabs)->map(fn ($query) => $query()->count())->all();
        // Past their needed-by date, across Ready and Partly: said once above the list, since they lead it anyway.
        $overdue = $queues->awaitingFulfilment($user)->whereNotNull('required_by_date')->whereDate('required_by_date', '<', now()->toDateString())->count();
        $activeRequests = $this->search($tabs[$tab](), $request->query('q'))->with(['items', 'events'])
            ->orderByRaw('CASE WHEN required_by_date IS NULL THEN 1 ELSE 0 END')->orderBy('required_by_date')
            ->orderBy($tab === 'returns' ? 'return_requested_at' : 'approved_at')->orderBy('id')->get();
        $presenter->preloadLines($activeRequests);
        $office = Location::find(app(TenantContext::class)->locationId)?->name ?? '';

        return view('govstore::fulfillment.index', compact('tab', 'counts', 'overdue', 'activeRequests', 'office', 'presenter'));
    }

    /** Issue items (board 10): quantities prefilled with what can be issued, free serials listed for scanning. */
    public function show($id, RequestQueues $queues, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkStorekeeperAccess();
        $serviceRequest = ServiceRequest::with(['items', 'events'])->findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);
        if (! ($serviceRequest->office_id && in_array($serviceRequest->approval_status, RequestWorkflow::APPROVED, true)
            && in_array($serviceRequest->fulfillment_status, RequestWorkflow::OPEN_FULFILLMENT, true))) {
            // Already issued or closed (perhaps by a colleague): show where it went instead of a bare 409.
            return redirect()->route('gov.requests.fulfillment_register.show', $serviceRequest->id)
                ->withErrors(['request' => $presenter->t('request_refused_not_open')]);
        }
        $presenter->preloadLines(collect([$serviceRequest]));
        $inventory = app(RequestInventory::class);
        $availableAssets = [];
        $lines = [];
        foreach ($serviceRequest->items->where('line_approval_status', 'approved') as $item) {
            $type = $item->fulfilled_type ?: $item->requested_type;
            $itemId = $item->fulfilled_id ?: $item->requested_id;
            $remaining = max(0, $item->approved_qty - $item->issued_qty);
            try {
                $model = $inventory->validateItem($type, $itemId);
                $free = $inventory->available($type, $model, (int) $serviceRequest->office_id, $item->id);
            } catch (HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $model = null;
                $free = 0;
            }
            if ($type === 'asset_model') {
                $query = Asset::with('location')->where('model_id', $itemId)->where('location_id', $serviceRequest->office_id)
                    ->whereNull('assigned_to')->where('requestable', 1)->whereHas('status', fn ($q) => $q->where('deployable', 1)->where('archived', 0));
                $inventory->scopeCompany($query, 'assets.company_id');
                $availableAssets[$item->id] = $query->orderBy('asset_tag')->get();
            }
            $lines[$item->id] = ['type' => $type, 'remaining' => $remaining, 'free' => $free,
                // P4: prefilled with min(remaining, free) so the usual case is one press.
                'prefill' => min($remaining, $free), 'price' => $model?->purchase_cost];
        }
        $waitingCount = $queues->awaitingFulfilment(auth()->user())->where('id', '!=', $serviceRequest->id)->count();
        $version = app(FulfillmentService::class)->issueVersion($serviceRequest, $serviceRequest->items);
        $issuer = $presenter->name(auth()->id());

        return view('govstore::fulfillment.show', compact('serviceRequest', 'availableAssets', 'lines', 'waitingCount', 'version', 'issuer', 'presenter'));
    }

    public function process(Request $request, $id, FulfillmentService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkStorekeeperAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);

        $request->validate([
            'issue' => 'required|array',
            'substitutions' => 'nullable|array',
            'notes' => 'nullable|string|max:2000',
            'handed_to' => 'nullable|in:requester,other',
            'handed_to_name' => 'nullable|required_if:handed_to,other|string|max:255',
            'version' => 'nullable|string|size:40',
        ]);
        $handedTo = $request->input('handed_to') === 'other' ? trim((string) $request->input('handed_to_name')) : null;

        return $this->attempt(fn () => $service->issueItems($serviceRequest, auth()->user(), $request->input('issue'),
            array_filter($request->input('substitutions', [])), $request->input('notes'), $handedTo ?: null, $request->input('version')),
            fn () => $presenter->t('issued_done', ['name' => $handedTo ?: $presenter->name($serviceRequest->requested_by)]),
            route('gov.requests.fulfillment.index'));
    }

    public function close(Request $request, $id, FulfillmentService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkStorekeeperAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);
        $request->validate(['reason' => 'required|string|min:5|max:2000', 'reason_code' => 'nullable|in:out_of_stock,discontinued,not_needed,other']);

        return $this->attempt(fn () => $service->forceClose($serviceRequest, auth()->user(), $request->input('reason'), $request->input('reason_code')),
            fn () => $presenter->t('closed_done', ['number' => $serviceRequest->request_number, 'name' => $presenter->name($serviceRequest->requested_by)]),
            route('gov.requests.fulfillment.index'));
    }
}
