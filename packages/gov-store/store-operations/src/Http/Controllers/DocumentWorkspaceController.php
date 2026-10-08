<?php

namespace GovStore\StoreOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Policies\DocumentPolicy;
use GovStore\StoreOperations\Services\CapabilityRegistry;
use GovStore\StoreOperations\Services\DocumentValidationService;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use GovStore\StoreOperations\Services\ProductResolver;
use GovStore\StoreOperations\Services\ProfileCompilerService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DocumentWorkspaceController extends Controller
{
    public function __construct(
        protected ProductResolver $productResolver,
        protected GoodsReceiptService $receiptService,
        protected PostingPipelineManager $pipelineManager,
        protected DocumentValidationService $validationService,
        protected DocumentPolicy $policy,
    ) {}

    private function document(string $type, string $id, string $action = 'view', bool $lock = false): Document
    {
        $query = Document::whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $document = $query->firstOrFail();
        $this->policy->check($document, $type, $action);

        return $document;
    }

    public function post(Request $request, string $type, string $id)
    {
        $candidate = $this->document($type, $id, 'post');
        try {
            // Verify external programme state before holding the document or ledger locks.
            // DRAFT references are about to be replaced by the submitted form; READY uses
            // its saved references because the posted payload cannot edit it.
            $trackingCode = $candidate->status === 'DRAFT'
                ? collect($request->input('references', []))->firstWhere('reference_type', 'Special Allocation')['reference_number'] ?? null
                : $candidate->references()->where('reference_type', 'Special Allocation')->value('reference_number');
            $trackingErrors = $this->validationService->validateTrackingReference($trackingCode, (int) $candidate->location_id);
            if ($trackingErrors) {
                throw ValidationException::withMessages(['items' => collect($trackingErrors)->flatten()->all()]);
            }

            DB::transaction(function () use ($request, $type, $id, $trackingCode) {
                $document = $this->document($type, $id, 'post', true);
                if ($document->status === 'DRAFT') {
                    $this->persistDraft($request, $document);
                }
                $document->refresh();
                // A concurrent edit/READY transition must not substitute an unverified reference.
                $savedTrackingCode = $document->references()->where('reference_type', 'Special Allocation')->value('reference_number');
                abort_unless(($savedTrackingCode ?: null) === ($trackingCode ?: null), 409);
                $completion = $this->validationService->evaluateDocument($document);
                if (! $document->items()->exists() || ! $completion['is_valid']) {
                    $messages = collect($completion['checklist'])->where('passed', false)->pluck('label')->all();
                    throw ValidationException::withMessages(['items' => $messages ?: [__('tenantops::access.validation_failed')]]);
                }
                $errors = $this->validationService->validateDocument($document, $request->all(), false);
                if ($errors) {
                    throw ValidationException::withMessages(['items' => collect($errors)->flatten()->all()]);
                }
                $this->pipelineManager->materialize($document, auth()->id());
            });

            return redirect()->route('storeops.documents.workspace', compact('type', 'id'))->with('success', __('tenantops::access.post'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->failure($request, $e);
        }
    }

    public function hub(Request $request)
    {
        $filter = $request->validate(['filter' => 'nullable|in:all,drafts,posted'])['filter'] ?? 'all';
        $documents = Document::with(['creator', 'references'])->when($filter === 'drafts', fn ($query) => $query
            ->where('status', 'DRAFT')->where('managed_by', auth()->id()))
            ->when($filter === 'posted', fn ($query) => $query->where('status', 'POSTED'))
            ->orderByDesc('created_at')->paginate(20)->withQueryString();
        $notices = DB::table('gov_document_notices as notice')->join('gov_documents as document', 'document.id', '=', 'notice.document_id')
            ->where('notice.user_id', auth()->id())->where('document.location_id', app(TenantContext::class)->locationId)
            ->where('document.company_id', app(TenantContext::class)->companyId)
            ->orderByDesc('notice.id')->limit(20)->get(['document.document_number', 'notice.event_key']);

        return view('storeops::operations.hub', compact('documents', 'filter', 'notices'));
    }

    public function initialize(Request $request)
    {
        $data = $request->validate(['document_type' => 'required|in:receipt,issue,adjustment,transfer']);
        abort_unless(app(TenantContext::class)->locationId, 422);
        try {
            $draft = $this->receiptService->saveDraft([], [], auth()->id(), null, $data['document_type']);

            return redirect()->route('storeops.documents.workspace', ['type' => $draft->type, 'id' => $draft->id]);
        } catch (\Throwable $e) {
            return $this->failure($request, $e);
        }
    }

    public function workspace(string $type, string $id)
    {
        $document = $this->document($type, $id)->load(['items.product', 'items.metadata', 'timelines', 'creator']);
        $ledgerOpen = DB::table('gov_store_ledger_openings')->where('location_id', $document->location_id)->exists();
        $officeRecipients = collect();
        if ($document->type === 'issue' && Schema::hasTable('gov_office_memberships')) {
            $officeRecipients = DB::table('gov_office_memberships as membership')
                ->join('users', 'users.id', '=', 'membership.user_id')
                ->where('membership.location_id', $document->location_id)
                ->where('membership.status', 'active')
                ->whereNull('users.deleted_at')
                ->orderBy('users.last_name')->orderBy('users.first_name')
                ->get(['users.id', 'users.first_name', 'users.last_name', 'users.username']);
        }
        $adjustmentSources = $document->type === 'adjustment'
            ? Document::whereIn('type', ['receipt', 'issue', 'adjustment'])->where('status', 'POSTED')
                ->orderByDesc('posted_at')->get(['id', 'document_number'])
            : collect();

        $suppliers = $document->type === 'receipt'
            ? \App\Models\Supplier::orderBy('name')->get(['id', 'name']) : collect();

        $transferOffices = collect();
        if ($document->type === 'transfer') {
            foreach (\App\Models\Location::withoutGlobalScopes()->whereNull('deleted_at')
                ->where('company_id', $document->company_id)->where('id', '!=', $document->location_id)->orderBy('name')->get(['id', 'name']) as $office) {
                $proposal = clone $document;
                $proposal->destination_location_id = $office->id;
                try {
                    app(\GovStore\StoreOperations\Services\TransferPostingService::class)->atDestination($proposal, auth()->id(), fn () => true);
                    $transferOffices->push($office);
                } catch (\Illuminate\Auth\Access\AuthorizationException $exception) {
                    // Only offices with live destination posting authority are selectable.
                }
            }
        }
        return view('storeops::operations.workspace', compact('document', 'type', 'officeRecipients', 'adjustmentSources', 'suppliers', 'transferOffices', 'ledgerOpen'));
    }

    public function saveDraft(Request $request, string $type, string $id)
    {
        $this->document($type, $id, 'draft');
        try {
            $document = DB::transaction(function () use ($request, $type, $id) {
                $document = $this->document($type, $id, 'draft', true);
                $this->persistDraft($request, $document);

                return $document->refresh();
            });
            $validation = $this->validationService->evaluateDocument($document);

            return $request->ajax() || $request->expectsJson()
                ? response()->json(['status' => 'success', 'validation' => $validation])
                : back()->with('success', __('tenantops::access.save'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->failure($request, $e);
        }
    }

    private function persistDraft(Request $request, Document $document): void
    {
        $request->validate(['items' => 'nullable|array', 'items.*.qty' => 'required|integer|min:0|max:99999',
            'items.*.unit_cost' => 'nullable|numeric|min:0', 'references' => 'nullable|array',
            'references.*.reference_type' => 'required|string|max:100',
            'references.*.reference_number' => 'nullable|string|max:255',
            'references.*.reference_date' => 'nullable|date',
            'supplier_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'issued_to_user_id' => 'nullable|integer|exists:users,id', 'issue_department' => 'nullable|string|max:150']);
        if ($document->type === 'adjustment') {
            $request->validate([
                'adjustment_reason' => 'required|in:PHYSICAL_COUNT,DAMAGE,LOSS,EXPIRED,CORRECTION',
                'source_document_id' => 'required|uuid|exists:gov_documents,id',
            ]);
            $source = Document::whereKey($request->input('source_document_id'))->firstOrFail();
            abort_unless(in_array($source->type, ['receipt', 'issue', 'adjustment'], true) && $source->status === 'POSTED'
                && (int) $source->location_id === (int) $document->location_id
                && (int) $source->company_id === (int) $document->company_id, 404);
        }
        if ($document->type === 'issue') {
            if ($request->filled('issued_to_user_id')) {
                abort_unless(Schema::hasTable('gov_office_memberships')
                    && DB::table('gov_office_memberships')->where('user_id', $request->input('issued_to_user_id'))
                        ->where('location_id', $document->location_id)->where('status', 'active')->exists()
                    && DB::table('users')->where('id', $request->input('issued_to_user_id'))->whereNull('deleted_at')->exists(),
                    422, __('storeops::storeops.issue_recipient_invalid'));
            }
            abort_unless($request->filled('issued_to_user_id') || $request->filled('issue_department'), 422,
                __('storeops::storeops.issue_recipient_required'));
        }
        if ($document->type === 'adjustment') {
            foreach ($request->input('items', []) as $item) {
                abort_unless(in_array(data_get($item, 'meta.0.adjustment_direction'), ['IN', 'OUT'], true), 422,
                    __('storeops::storeops.adjustment_direction_required'));
            }
        }
        if ($document->type === 'transfer') {
            $data = $request->validate(['destination_location_id' => 'required|integer', 'transfer_reason' => 'required|string|min:5|max:500']);
            $document->destination_location_id = $data['destination_location_id'];
            app(\GovStore\StoreOperations\Services\TransferPostingService::class)->atDestination($document, auth()->id(), fn () => true);
        }
        $rawLines = [];
        foreach ($request->input('items', []) as $item) {
            if (empty($item['id'])) {
                continue;
            }
            [$shortType, $productId] = $this->productId($item['id']);
            $rawLines[] = ['type' => $shortType, 'id' => $productId, 'qty' => $item['qty'], 'unit_cost' => $item['unit_cost'] ?? 0];
        }
        $header = $request->only('purchase_type', 'source_document_id', 'adjustment_reason', 'issued_to_user_id', 'issue_department');
        if ($document->type === 'receipt') {
            $header['supplier_id'] = $request->input('supplier_id');
        }
        if ($document->type === 'transfer') {
            $header = array_merge($header, $request->only('destination_location_id', 'transfer_reason'));
        }
        $this->receiptService->saveDraft($header,
            $rawLines, auth()->id(), $document, $document->type);
        foreach ($request->input('items', []) as $item) {
            if (empty($item['id'])) {
                continue;
            }
            [$shortType, $productId] = $this->productId($item['id']);
            $dbItem = $document->items()->where('product_type', $shortType)->where('product_id', $productId)->first();
            if (! $dbItem) {
                continue;
            }
            foreach ($item['meta'] ?? [] as $rowIndex => $meta) {
                foreach ($meta as $fieldKey => $value) {
                    if ($value !== null && $value !== '') {
                        $dbItem->metadata()->create(['field_key' => $fieldKey, 'value' => $value, 'row_index' => $rowIndex]);
                    }
                }
            }
        }
        $document->references()->delete();
        foreach ($request->input('references', []) as $ref) {
            if (! empty($ref['reference_number'])) {
                $document->references()->create([
                    'reference_type' => $ref['reference_type'] ?? 'Challan', 'reference_number' => $ref['reference_number'], 'reference_date' => ($ref['reference_date'] ?? null) ?: null,
                ]);
            }
        }
    }

    private function productId(string $id): array
    {
        if (! str_contains($id, '_')) {
            return ['consumable', $id];
        }
        [$rawType, $productId] = explode('_', $id, 2);

        return [strtolower(class_basename($rawType)), $productId];
    }

    public function preview(string $type, string $id)
    {
        $document = $this->document($type, $id)->load(['items.product', 'references']);

        return response()->json([
            'lines' => $document->items->count(), 'total_qty' => $document->items->sum('quantity'),
            'total_value' => number_format($document->items->sum(fn ($item) => $item->quantity * ($item->unit_cost ?? 0)), 2),
            'reference' => $document->references->map(fn ($r) => $r->reference_type.': '.$r->reference_number)->implode(' | '),
            'items' => $document->items->map(fn ($item) => ['name' => $item->product?->name ?? $item->product_id, 'quantity' => $item->quantity])->all(),
        ]);
    }

    public function takeover(Request $request, string $type, string $id)
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($type, $id, $request) {
            $document = $this->document($type, $id, 'takeover', true);
            $previousManagerId = $document->managed_by;
            $document->update(['managed_by' => auth()->id()]);
            $document->timelines()->create([
                'state' => 'DRAFT',
                'user_id' => auth()->id(),
                'notes' => __('storeops::storeops.takeover_timeline', [
                    'previous_manager' => $previousManagerId ?? __('storeops::storeops.no_previous_manager'),
                    'reason' => $request->input('reason'),
                ]),
            ]);
            if ($previousManagerId && (int) $previousManagerId !== (int) auth()->id()) {
                DB::table('gov_document_notices')->insert([
                    'document_id' => $document->id, 'user_id' => $previousManagerId,
                    'actor_id' => auth()->id(), 'event_key' => 'takeover', 'created_at' => now(),
                ]);
            }
        });

        return back()->with('success', __('tenantops::access.takeover_done'));
    }

    public function print(string $type, string $id)
    {
        $document = $this->document($type, $id)->load(['items.stockable', 'timelines', 'creator']);

        return view('storeops::operations.print', compact('document', 'type'));
    }

    public function searchProducts(Request $request)
    {
        $document = $request->filled('document_id') ? Document::findOrFail($request->input('document_id')) : null;
        if ($document) {
            $this->policy->check($document, $document->type);
        }
        $results = $this->productResolver->search($request->input('q', ''))->map(function ($item) {
            $modelClass = $item['type_raw'];

            return ['id' => $item['type_raw'].'_'.$item['id'], 'text' => $item['name'].' ('.$item['type_label'].')',
                'current_stock' => $item['current_stock'], 'category_id' => $item['category_id']];
        })->filter(fn ($item) => ! $document || ! in_array($document->type, ['issue', 'adjustment', 'transfer'], true)
            || ! str_contains($item['id'], 'AssetModel'))->values();

        return response()->json(['results' => $results]);
    }

    public function productProfile(Request $request, string $type, int $id)
    {
        try {
            return response()->json(app(ProfileCompilerService::class)->compileItem(strtolower(class_basename($type)), $id));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->failure($request, $e);
        }
    }

    public function uploadAttachment(Request $request, string $type, string $id)
    {
        $request->validate(['file' => 'required|file|mimes:pdf,png,jpg,jpeg,docx,xlsx|max:10240', 'category' => 'required|string|max:100']);
        $this->document($type, $id, 'draft');
        $path = null;
        try {
            $attachment = DB::transaction(function () use ($request, $type, $id, &$path) {
                $document = $this->document($type, $id, 'draft', true);
                $file = $request->file('file');
                $path = $file->store('app/gov-store/attachments', 'local');

                return $document->attachments()->create(['file_path' => $path, 'disk' => 'local',
                    'original_name' => '['.strtoupper($request->input('category')).'] '.$file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(), 'uploaded_by' => auth()->id()]);
            });

            return response()->json(['status' => 'success', 'attachment' => ['id' => $attachment->id, 'name' => $attachment->original_name,
                'url' => route('storeops.documents.attachments.download', ['type' => $type, 'id' => $id, 'attachmentId' => $attachment->id])]]);
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            return $this->failure($request, $e);
        }
    }

    public function downloadAttachment(string $type, string $id, string $attachmentId)
    {
        $attachment = $this->document($type, $id)->attachments()->findOrFail($attachmentId);

        return Storage::disk($attachment->disk)->download($attachment->file_path, $attachment->original_name);
    }

    public function deleteAttachment(Request $request, string $type, string $id, string $attachmentId)
    {
        $this->document($type, $id, 'draft');
        DB::transaction(function () use ($type, $id, $attachmentId) {
            $attachment = $this->document($type, $id, 'draft', true)->attachments()->findOrFail($attachmentId);
            $disk = $attachment->disk;
            $path = $attachment->file_path;
            $attachment->delete();
            DB::afterCommit(fn () => Storage::disk($disk)->delete($path));
        });

        return response()->json(['status' => 'success']);
    }

    public function renderMeta(Request $request)
    {
        $request->validate(['product_type' => 'required|string|max:100', 'product_id' => 'required|integer|min:1',
            'quantity' => 'required|integer|min:1|max:1000', 'row_index' => 'required|integer|min:0|max:499',
            'document_id' => 'required|uuid']);
        $item = null;
        $normalizedType = strtolower(class_basename($request->input('product_type')));
        if ($id = $request->input('document_id')) {
            $document = Document::findOrFail($id);
            $this->policy->check($document, $document->type);
            $item = $document->items()->where('product_type', $normalizedType)->where('product_id', $request->input('product_id'))->first();
            if ($item) {
                $item->quantity = (int) $request->input('quantity', 1);
            }
        }
        try {
            $compiled = app(ProfileCompilerService::class)->compileItem($normalizedType, $request->input('product_id'));
            $html = '';
            foreach ($compiled as $code => $meta) {
                if (isset($document) && $document->type === 'transfer') {
                    continue;
                }
                if (($meta['enforced'] ?? false) === true) {
                    $html .= CapabilityRegistry::make($code)->renderUI($item,
                        ['config' => $meta['config'] ?? [], 'row_index' => $request->input('row_index', 0), 'quantity' => (int) $request->input('quantity', 1)]);
                }
            }
            if (isset($document) && $document->type === 'adjustment') {
                $html .= view('storeops::capabilities.adjustment_direction', [
                    'item' => $item,
                    'config' => ['row_index' => $request->input('row_index', 0)],
                ])->render();
            }

            if (isset($document) && $document->type === 'transfer' && $document->destination_location_id) {
                $targets = app(\GovStore\StoreOperations\Services\TransferPostingService::class)->destinationItems(
                    $document, $normalizedType, (int) $request->input('product_id'), auth()->id());
                $html .= view('storeops::capabilities.transfer_item', ['item' => $item, 'targets' => $targets,
                    'rowIndex' => (int) $request->input('row_index', 0), 'isDraft' => $document->status === 'DRAFT'])->render();
            }
            return response()->json(['html' => $html, 'has_requirements' => $html !== '']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException|HttpExceptionInterface|\Illuminate\Auth\Access\AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return $this->failure($request, $e);
        }
    }

    public function voidDraft(Request $request, string $type, string $id)
    {
        $request->validate(['reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($type, $id, $request) {
            $document = $this->document($type, $id, 'draft', true);
            $document->transitionTo(\GovStore\StoreOperations\Enums\DocumentState::CANCELLED, (int) auth()->id(), $request->input('reason'));
        });

        return redirect()->route('storeops.hub')->with('success', __('storeops::storeops.draft_voided'));
    }

    private function failure(Request $request, \Throwable $e)
    {
        $reference = (string) Str::uuid();
        Log::error('Store document operation failed', ['reference_id' => $reference, 'actor' => auth()->id(), 'exception' => $e]);
        $message = __('tenantops::access.failed', ['reference' => $reference]);

        return $request->ajax() || $request->expectsJson() ? response()->json(['error' => $message, 'reference_id' => $reference], 500) : back()->with('error', $message);
    }
}
