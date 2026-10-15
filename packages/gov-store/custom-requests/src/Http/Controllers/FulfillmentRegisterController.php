<?php

namespace GovStore\CustomRequests\Http\Controllers;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\RequestReturnService;
use GovStore\CustomRequests\Support\RequestPresenter;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Controllers\Concerns\RespondsWithRefusals;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FulfillmentRegisterController extends Controller
{
    use RespondsWithRefusals;

    private const SHOW = ['all', 'issued', 'closed', 'return'];

    private function checkAccess()
    {
        abort_unless(app(GovAccess::class)->permitsRequest(auth()->user(), 'storeops.documents.view'), 403);
    }

    /** Issue register (board 11, F13): filters and pages of 25 instead of every record at once. */
    public function index(Request $request, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkAccess();
        $filters = $request->validate(['q' => 'nullable|string|max:100', 'show' => 'nullable|in:'.implode(',', self::SHOW),
            'from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'page' => 'nullable|integer|min:1']);
        $user = auth()->user();
        $show = $filters['show'] ?? 'all';

        $query = ServiceRequest::with(['items', 'events'])
            ->whereIn('fulfillment_status', ['issued', 'partially_issued', 'closed', 'cannot_fulfill'])
            ->whereNotIn('approval_status', ['rejected', 'cancelled'])
            ->when(! $user->isSuperUser(), fn ($q) => $q->where('office_id', app(TenantContext::class)->locationId))
            ->when($show === 'issued', fn ($q) => $q->where('fulfillment_status', 'issued'))
            ->when($show === 'closed', fn ($q) => $q->whereIn('fulfillment_status', ['closed', 'cannot_fulfill']))
            ->when($show === 'return', fn ($q) => $q->whereNotNull('return_requested_at'))
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('closed_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('closed_at', '<=', $filters['to']))
            ->when(filled($filters['q'] ?? null), function ($q) use ($filters) {
                $term = trim($filters['q']);
                $q->where(fn ($q) => $q->where('request_number', 'like', "%{$term}%")
                    ->orWhereHas('requester', fn ($u) => $u->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")));
            })
            ->orderByDesc('closed_at')->orderByDesc('updated_at')->orderByDesc('id');
        $completedRequests = $query->paginate(25)->withQueryString();
        $presenter->preloadLines($completedRequests->getCollection());
        $documents = GoodsIssue::query()->where('reference_type', ServiceRequest::class)
            ->whereIn('reference_id', $completedRequests->getCollection()->pluck('id'))->orderBy('created_at')->get(['reference_id', 'issue_no'])
            ->groupBy('reference_id');
        $documentLinks = $this->documentLinks($documents->flatten(1)->pluck('issue_no'));

        return view('govstore::fulfillment-register.index', compact('completedRequests', 'documents', 'documentLinks', 'filters', 'show', 'presenter'));
    }

    /** One issue record: the storekeeper's return task, goods issue lines with item names, a dated history. */
    public function show($id, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $this->checkAccess();
        $serviceRequest = ServiceRequest::with(['items', 'events', 'returnDocument'])->findOrFail($id);
        abort_unless(auth()->user()->isSuperUser() || (int) $serviceRequest->office_id === app(TenantContext::class)->locationId, 404);
        $presenter->preloadLines(collect([$serviceRequest]));

        $goodsIssues = GoodsIssue::with(['items', 'creator'])
            ->where('reference_type', ServiceRequest::class)
            ->where('reference_id', $id)
            ->orderBy('created_at')
            ->get();
        // Goods issue lines store class names (App\Models\Asset, a morph key, …): resolve them to item names (F13).
        $presenter->preloadItems($goodsIssues->flatMap->items->map(fn ($line) => [$this->morphKey($line->stockable_type), $line->stockable_id]));
        $canDraftReturn = app(GovAccess::class)->decide(auth()->user(), 'storeops.documents.draft')->allowed;
        $documentLinks = $this->documentLinks($goodsIssues->pluck('issue_no'));

        return view('govstore::fulfillment-register.show', compact('serviceRequest', 'goodsIssues', 'documentLinks', 'presenter', 'canDraftReturn'));
    }

    /** Goods issue numbers that are store documents (bulk issues) link to their workspace; serial issues have none. */
    private function documentLinks($numbers): array
    {
        $numbers = collect($numbers)->filter()->unique()->values();

        return $numbers->isEmpty() ? [] : Document::query()->where('type', 'issue')->whereIn('document_number', $numbers)
            ->pluck('id', 'document_number')->map(fn ($id) => route('storeops.documents.workspace', ['type' => 'issue', 'id' => $id]))->all();
    }

    public static function morphKey(?string $type): string
    {
        $type = (string) $type;

        return match (true) {
            str_ends_with($type, '\\Asset') || $type === 'asset' => 'asset',
            str_ends_with($type, '\\AssetModel') => 'asset_model',
            str_ends_with($type, '\\Accessory') => 'accessory',
            str_ends_with($type, '\\Consumable') => 'consumable',
            default => strtolower(class_basename($type)),
        };
    }

    public function draftReturn($id, RequestReturnService $service, ?RequestPresenter $presenter = null)
    {
        $presenter ??= app(RequestPresenter::class);
        $document = null;
        $response = $this->attempt(function () use (&$document, $service, $id) {
            $document = $service->draftReceipt((int) $id, auth()->user());
        }, $presenter->t('return_drafted_flash'));

        return $document ? redirect()->route('storeops.documents.workspace', ['type' => 'receipt', 'id' => $document->id])
            ->with('success', $presenter->t('return_drafted_flash')) : $response;
    }
}
