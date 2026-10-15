<?php

namespace GovStore\CustomRequests\Http\Controllers;

use App\Models\Accessory;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Location;
use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\CustomRequests\Services\RequestInventory;
use GovStore\CustomRequests\Services\RequestQueues;
use GovStore\CustomRequests\Support\RequestPresenter;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Controllers\Concerns\RespondsWithRefusals;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class GovApprovalController extends Controller
{
    use RespondsWithRefusals;

    private const TABS = ['waiting', 'overdue', 'decided'];

    private const TYPE_CLASSES = ['asset_model' => AssetModel::class, 'accessory' => Accessory::class, 'consumable' => Consumable::class];

    private function checkApproverAccess()
    {
        abort_unless(app(GovAccess::class)->permitsRequest(auth()->user(), 'requests.approve'), 403);
    }

    private function checkSystemAdminAccess()
    {
        abort_unless(app(GovAccess::class)->decide(auth()->user(), 'requests.configure')->allowed, 403);
    }

    /** Approvals (board 7): Waiting for you · Overdue · Decided by you, from the same queries as the menu badge. */
    public function index(Request $request, RequestQueues $queues, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkApproverAccess();
        $user = auth()->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'waiting';
        $counts = ['waiting' => $queues->awaitingApproval($user)->count(), 'overdue' => $queues->overdue($user)->count(),
            'decided' => $queues->decidedBy($user)->count()];

        $pendingRequests = collect();
        $processedRequests = null;
        $stock = [];
        if ($tab === 'decided') {
            $processedRequests = $queues->decidedBy($user)->with(['items', 'events'])->orderByDesc('updated_at')->orderByDesc('id')->paginate(25)->withQueryString();
            $presenter->preloadLines($processedRequests->getCollection());
        } else {
            $pendingRequests = ($tab === 'overdue' ? $queues->overdue($user) : $queues->awaitingApproval($user))
                ->with(['items', 'events'])->orderBy('submitted_at')->orderBy('id')->get();
            $presenter->preloadLines($pendingRequests);
            $stock = $this->stockWarnings($pendingRequests);
        }
        $office = Location::find(app(TenantContext::class)->locationId)?->name ?? '';

        return view('govstore::admin.index', compact('tab', 'counts', 'pendingRequests', 'processedRequests', 'stock', 'office', 'presenter'));
    }

    /** "only 4 toner free" per waiting request: the first line asking for more than is free. */
    private function stockWarnings($requests): array
    {
        $inventory = app(RequestInventory::class);
        $presenter = app(RequestPresenter::class);
        $warnings = [];
        foreach ($requests as $request) {
            foreach ($request->items->where('line_approval_status', 'pending') as $line) {
                try {
                    $free = $inventory->available($line->requested_type, $inventory->validateItem($line->requested_type, $line->requested_id), $request->office_id);
                } catch (HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException) {
                    continue;
                }
                if ($free < $line->requested_qty) {
                    $warnings[$request->id] = $presenter->t($free > 0 ? 'row_stock_low' : 'row_stock_none', ['count' => $free, 'item' => $presenter->lineName($line)]);
                    break;
                }
            }
        }

        return $warnings;
    }

    /** Decide (board 8): stock, the requester's last issue of the item and the rule beside every line. */
    public function show($id, RequestQueues $queues, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkApproverAccess();
        $serviceRequest = ServiceRequest::with(['items', 'events'])->findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);
        $user = auth()->user();
        $pending = in_array($serviceRequest->approval_status, RequestWorkflow::PENDING, true);
        $stageAbility = $serviceRequest->approval_status === 'pending_primary' ? 'requests.approve.primary' : 'requests.approve.final';
        $canDecide = $serviceRequest->office_id && $pending
            && (int) $serviceRequest->requested_by !== (int) $user->id
            && ($serviceRequest->approval_status !== 'pending_final' || (int) $serviceRequest->primary_decided_by !== (int) $user->id)
            && app(GovAccess::class)->decide($user, $stageAbility)->allowed;
        $whyNot = $canDecide ? null : match (true) {
            ! $pending => 'view_only_decided',
            (int) $serviceRequest->requested_by === (int) $user->id => 'view_only_own',
            $serviceRequest->approval_status === 'pending_final' && (int) $serviceRequest->primary_decided_by === (int) $user->id => 'view_only_primary',
            default => 'view_only_stage',
        };
        $presenter->preloadLines(collect([$serviceRequest]));

        $inventory = app(RequestInventory::class);
        $facts = [];
        foreach ($serviceRequest->items as $line) {
            try {
                $free = $inventory->available($line->requested_type, $inventory->validateItem($line->requested_type, $line->requested_id), (int) $serviceRequest->office_id);
            } catch (HttpExceptionInterface|\Illuminate\Database\Eloquent\ModelNotFoundException) {
                $free = null;
            }
            $previous = RequestItem::query()->where('requested_type', $line->requested_type)->where('requested_id', $line->requested_id)
                ->where('issued_qty', '>', 0)->where('request_id', '!=', $serviceRequest->id)
                ->whereHas('request', fn ($q) => $q->where('requested_by', $serviceRequest->requested_by)->where('office_id', $serviceRequest->office_id))
                ->latest('updated_at')->first();
            $reason = collect($serviceRequest->resolved_reason['lines'] ?? [])
                ->first(fn ($r) => ($r['type'] ?? null) === $line->requested_type && (int) ($r['id'] ?? 0) === (int) $line->requested_id);
            $facts[$line->id] = ['free' => $free, 'previous' => $previous, 'reason' => $reason ? $presenter->reasonSentence($reason) : null];
        }
        $finalApprover = $serviceRequest->approval_status === 'pending_primary' && $serviceRequest->resolved_policy === 'PRIMARY_AND_FINAL'
            ? $presenter->finalApproverId($serviceRequest) : null;
        $waiting = $canDecide ? $queues->awaitingApproval($user)->where('id', '!=', $serviceRequest->id)->orderBy('submitted_at')->orderBy('id')->first() : null;
        $waitingCount = $canDecide ? $queues->awaitingApproval($user)->where('id', '!=', $serviceRequest->id)->count() : 0;

        return view('govstore::admin.show', compact('serviceRequest', 'canDecide', 'whyNot', 'facts', 'finalApprover', 'waiting', 'waitingCount', 'presenter'));
    }

    /**
     * Each line arrives as a choice (as_asked · fewer · decline) with a quantity and an optional reason (D5:
     * reasons stay optional). It becomes the service's approved/rejected decision; a stale decision comes
     * back to the page with who decided first, and the person's choices kept.
     */
    public function process(Request $request, $id, ApprovalService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkApproverAccess();
        $serviceRequest = ServiceRequest::findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);
        $request->validate(['items' => 'required|array', 'items.*' => 'array',
            'items.*.choice' => 'nullable|in:as_asked,fewer,decline', 'items.*.qty' => 'nullable|integer|min:0|max:'.RequestWorkflow::MAX_QUANTITY,
            'items.*.notes' => 'nullable|string|max:2000']);
        $lines = $serviceRequest->items->keyBy('id');
        $decisions = [];
        foreach ($request->input('items', []) as $lineId => $input) {
            $line = $lines->get((int) $lineId);
            $cap = $line ? ($serviceRequest->primary_decided_by ? min($line->requested_qty, max($line->approved_qty, 0) ?: $line->requested_qty) : $line->requested_qty) : 0;
            $choice = $input['choice'] ?? (($input['status'] ?? null) === 'rejected' ? 'decline' : 'fewer');
            $decisions[$lineId] = match ($choice) {
                'decline' => ['status' => 'rejected', 'qty' => 0],
                'as_asked' => ['status' => 'approved', 'qty' => $cap],
                default => ['status' => 'approved', 'qty' => (int) ($input['qty'] ?? 0)],
            } + ['notes' => filled($input['notes'] ?? null) ? trim($input['notes']) : null];
        }

        $result = null;

        return $this->attempt(function () use (&$result, $service, $serviceRequest, $decisions) {
            $result = $service->processDecision($serviceRequest, auth()->user(), $decisions);
        }, function () use (&$result, $presenter) {
            return match ($result->approval_status) {
                'pending_final' => $result->assigned_approver_id
                    ? $presenter->t('decided_first', ['number' => $result->request_number, 'name' => $presenter->name($result->assigned_approver_id)])
                    : $presenter->t('decided_first_unassigned', ['number' => $result->request_number]),
                'rejected' => $presenter->t('decided_rejected', ['number' => $result->request_number, 'name' => $presenter->name($result->requested_by)]),
                'partially_approved' => $presenter->t('decided_partly', ['number' => $result->request_number]),
                default => $presenter->t('decided_approved', ['number' => $result->request_number]),
            };
        }, route('gov.requests.admin.index'), route('gov.requests.admin.show', $serviceRequest->id));
    }

    /** Request rules (board 12): national rules, by category, with item exceptions found by name. */
    public function policiesIndex(RequestPresenter $presenter)
    {
        $this->checkSystemAdminAccess();
        $categories = Category::orderBy('name')->get();
        $policies = ApprovalPolicy::where('target_type', 'category')->get()->keyBy('target_id');
        $exceptions = ApprovalPolicy::where('target_type', '!=', 'category')->orderBy('target_type')->orderBy('target_id')->get();
        $presenter->preloadItems($exceptions->map(fn ($p) => [$p->target_type, $p->target_id]));
        $presenter->preloadPeople(ApprovalPolicy::query()->pluck('updated_by'));

        return view('govstore::admin.policies', compact('categories', 'policies', 'exceptions', 'presenter'));
    }

    /** Item search for exceptions: national scope, by name, any office. */
    public function policyItems(Request $request)
    {
        $this->checkSystemAdminAccess();
        $request->validate(['q' => 'nullable|string|max:200', 'type' => 'required|in:asset_model,accessory,consumable']);
        $term = trim((string) $request->input('q'));
        if (mb_strlen($term) < 2) {
            return response()->json([]);
        }
        $class = self::TYPE_CLASSES[$request->input('type')];

        return response()->json($class::with('category')->where('name', 'like', "%{$term}%")->orderBy('name')->limit(20)->get()
            ->map(fn ($item) => ['id' => $item->id, 'name' => $item->name, 'category' => $item->category?->name]));
    }

    public function policiesStore(Request $request, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkSystemAdminAccess();
        $data = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'target_type' => 'required_without:category_id|in:category,asset_model,accessory,consumable',
            'target_id' => 'required_without:category_id|integer|min:1',
            'policy_name' => 'required|in:AUTO_APPROVE,PRIMARY_ONLY,PRIMARY_AND_FINAL',
            'threshold_qty' => 'nullable|integer|min:1|max:10000',
            'threshold_value' => 'nullable|numeric|min:0.01|max:9999999999999',
        ]);
        $name = DB::transaction(function () use ($data) {
            $type = ! empty($data['category_id']) ? 'category' : $data['target_type'];
            $id = ! empty($data['category_id']) ? $data['category_id'] : $data['target_id'];
            // Rules are national (F7): an item exception names a catalogue item, not one office's stock.
            $target = $type === 'category' ? Category::whereKey($id)->lockForUpdate()->firstOrFail()
                : self::TYPE_CLASSES[$type]::whereKey($id)->lockForUpdate()->firstOrFail();
            ApprovalPolicy::updateOrCreate(['target_type' => $type, 'target_id' => $id], [
                'policy_name' => $data['policy_name'],
                // Thresholds only raise a rule to two approvers; they mean nothing on "Two approvers, always".
                'threshold_qty' => $data['policy_name'] === 'PRIMARY_AND_FINAL' ? null : ($data['threshold_qty'] ?? null),
                'threshold_value' => $data['policy_name'] === 'PRIMARY_AND_FINAL' ? null : ($data['threshold_value'] ?? null),
                'updated_by' => auth()->id(),
            ]);

            return $target->name;
        });

        return redirect()->route('gov.requests.admin.policies.index')->with('success', $presenter->t('rule_saved', ['name' => $name]));
    }

    public function policiesDestroy($id, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkSystemAdminAccess();
        $policy = ApprovalPolicy::whereKey((int) $id)->lockForUpdate()->firstOrFail();
        $name = $policy->target_type === 'category' ? Category::withTrashed()->find($policy->target_id)?->name
            : $presenter->itemName($policy->target_type, (int) $policy->target_id);
        $policy->delete();

        return redirect()->route('gov.requests.admin.policies.index')->with('success', $presenter->t('rule_removed', ['name' => $name]));
    }
}
