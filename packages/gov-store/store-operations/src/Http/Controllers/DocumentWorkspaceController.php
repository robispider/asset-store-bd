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
        $this->document($type, $id, 'post');
        try {
            DB::transaction(function () use ($request, $type, $id) {
                $document = $this->document($type, $id, 'post', true);
                if ($document->status === 'DRAFT') {
                    $this->persistDraft($request, $document);
                }
                $document->refresh();
                $completion = $this->validationService->evaluateDocument($document);
                if (! $document->items()->exists() || ! $completion['is_valid']) {
                    $messages = collect($completion['checklist'])->where('passed', false)->pluck('label')->all();
                    throw ValidationException::withMessages(['items' => $messages ?: [__('tenantops::access.validation_failed')]]);
                }
                $errors = $this->validationService->validateDocument($document, $request->all());
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
        $documents = Document::with('creator')->orderByDesc('created_at')->paginate(20);

        return view('storeops::operations.hub', compact('documents'));
    }

    public function initialize(Request $request)
    {
        $data = $request->validate(['document_type' => 'required|in:receipt,issue,adjustment']);
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

        return view('storeops::operations.workspace', compact('document', 'type', 'officeRecipients', 'adjustmentSources'));
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
        $request->validate(['items' => 'nullable|array', 'items.*.qty' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0', 'references' => 'nullable|array',
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
        $rawLines = [];
        foreach ($request->input('items', []) as $item) {
            if (empty($item['id'])) {
                continue;
            }
            [$shortType, $productId] = $this->productId($item['id']);
            $rawLines[] = ['type' => $shortType, 'id' => $productId, 'qty' => $item['qty'], 'unit_cost' => $item['unit_cost'] ?? 0];
        }
        $this->receiptService->saveDraft($request->only('purchase_type', 'source_document_id', 'adjustment_reason', 'issued_to_user_id', 'issue_department'),
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
        DB::transaction(function () use ($type, $id) {
            $document = $this->document($type, $id, 'takeover', true);
            $document->update(['managed_by' => auth()->id()]);
            $document->timelines()->create(['state' => 'DRAFT', 'user_id' => auth()->id(), 'notes' => __('tenantops::access.takeover_done')]);
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
        $results = $this->productResolver->search($request->input('q', ''))->map(function ($item) {
            $modelClass = $item['type_raw'];

            return ['id' => $item['type_raw'].'_'.$item['id'], 'text' => $item['name'].' ('.$item['type_label'].')',
                'current_stock' => $item['current_stock'], 'category_id' => $item['category_id']];
        });

        return response()->json(['results' => $results]);
    }

    public function productProfile(Request $request, string $type, int $id)
    {
        try {
            return response()->json(app(ProfileCompilerService::class)->compileItem(strtolower(class_basename($type)), $id));
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

            return response()->json(['html' => $html, 'has_requirements' => $html !== '']);
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
