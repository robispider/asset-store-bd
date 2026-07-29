<?php

namespace GovStore\StoreOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\StoreOperations\Services\GoodsIssueService;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use GovStore\StoreOperations\Services\ProductResolver;
use GovStore\StoreOperations\Services\DocumentValidationService;
use Illuminate\Support\Facades\Storage;
use Exception;

class DocumentWorkspaceController extends Controller
{
    protected ProductResolver $productResolver;
    protected GoodsReceiptService $receiptService;
    protected GoodsIssueService $issueService;
    protected PostingPipelineManager $pipelineManager;
    protected DocumentValidationService $validationService;

    /**
     * Dependency Injection via Constructor.
     */
    public function __construct(
        ProductResolver $productResolver, 
        GoodsReceiptService $receiptService,
        GoodsIssueService $issueService,
        PostingPipelineManager $pipelineManager,
        DocumentValidationService $validationService
    ) {
        $this->productResolver = $productResolver;
        $this->receiptService = $receiptService;
        $this->issueService = $issueService;
        $this->pipelineManager = $pipelineManager;
        $this->validationService = $validationService;
    }

    /**
     * Handles the final ledger posting and materialization.
     */
    public function post(Request $request, string $type, string $id)
    {
        $document = Document::findOrFail($id);

        try {
            // 1. Auto-save the latest grid values to the draft
            $this->saveDraft($request, $type, $id);
            $document->refresh();

            // 2. Run validations before materializing
            try {
                $validationErrors = $this->validationService->validateDocument($document, $request->all());

                if (!empty($validationErrors)) {
                    $errorMessages = [];
                    foreach ($validationErrors as $productName => $caps) {
                        foreach ($caps as $capErrors) {
                            foreach ($capErrors as $messages) {
                                $errorMessages[] = "[{$productName}] " . implode(' ', $messages);
                            }
                        }
                    }
                    return back()->with('error', 'Validation Failed: ' . implode(' | ', $errorMessages));
                }

                // 3. Execute the materialization pipeline (Kardex ledger and assets)
                $this->pipelineManager->materialize($document, auth()->id());

            } catch (\Throwable $e) {
                throw new \Error(
                    "POSTING CRASH: " . $e->getMessage() . 
                    " in " . $e->getFile() . " on line " . $e->getLine() . 
                    " | DB Snapshot: " . json_encode($document->compiled_profile_snapshot)
                );
            }

            return redirect()->route('storeops.documents.workspace', ['type' => $type, 'id' => $id])
                             ->with('success', 'Document finalized successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Renders the Operational Hub (Document Listings Dashboard).
     */
    public function hub(Request $request)
    {
        $documents = Document::with('creator')->orderBy('created_at', 'desc')->paginate(20);
        return view('storeops::operations.hub', compact('documents'));
    }

    /**
     * Instantly initializes a blank DRAFT document.
     */
    public function initialize(Request $request)
    {
        $type = $request->input('document_type', 'receipt');

        try {
            $draft = match($type) {
                'receipt' => $this->receiptService->saveDraft([], [], auth()->id()),
                'issue'   => $this->issueService->saveDraft([], [], auth()->id()),
                default   => abort(400, "Unsupported document type.")
            };

            return redirect()->route('storeops.documents.workspace', ['type' => $type, 'id' => $draft->id]);
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Renders the Unified Workspace Shell for drafting or viewing archives.
     */
    public function workspace(string $type, string $id)
    {
        $document = Document::with(['items.product', 'items.metadata', 'timelines', 'creator'])->findOrFail($id);
        return view('storeops::operations.workspace', compact('document', 'type'));
    }

    /**
     * Save the Document Draft (Now with Polymorphic References)
     */
    public function saveDraft(Request $request, string $type, string $id)
    {
        $document = Document::findOrFail($id);
        
        // 1. We no longer pull reference_no/date here, only purchase_type
        $headerData = $request->only(['purchase_type']);
        $rawLines = [];

        foreach ($request->input('items', []) as $rowId => $item) {
            if (isset($item['id']) && str_contains($item['id'], '_')) {
                [$rawType, $productId] = explode('_', $item['id']);
                $shortType = strtolower(class_basename($rawType));
            } else {
                $shortType = 'consumable';
                $productId = $item['id'] ?? 0;
            }

            $rawLines[] = [
                'type'      => $shortType,
                'id'        => $productId,
                'qty'       => $item['qty'] ?? 0,
                'unit_cost' => $item['unit_cost'] ?? 0.0,
            ];
        }

        try {
            // 2. Delegate base save for header and items
            match($type) {
                'receipt' => $this->receiptService->saveDraft($headerData, $rawLines, auth()->id(), $document),
                'issue'   => $this->issueService->saveDraft($headerData, $rawLines, auth()->id(), $document),
            };

            // 3. Persist custom metadata fields for line items
            foreach ($request->input('items', []) as $rowId => $item) {
                if (isset($item['id']) && str_contains($item['id'], '_')) {
                    [$rawType, $productId] = explode('_', $item['id']);
                    $shortType = strtolower(class_basename($rawType));
                } else {
                    $shortType = 'consumable';
                    $productId = $item['id'] ?? 0;
                }

                $dbItem = $document->items()
                    ->where('product_type', $shortType)
                    ->where('product_id', $productId)
                    ->first();

                if ($dbItem && isset($item['meta'])) {
                    $dbItem->metadata()->delete();

                    foreach ($item['meta'] as $rowIndex => $meta) {
                        foreach ($meta as $fieldKey => $value) {
                            if ($value === null || $value === '') {
                                continue;
                            }

                            $dbItem->metadata()->create([
                                'field_key' => $fieldKey,
                                'value'     => $value,
                                'row_index' => $rowIndex
                            ]);
                        }
                    }
                }
            }

            // 4. NEW: Sync Polymorphic Administrative References
            $document->references()->delete(); // Wipe old for clean sync
            $references = $request->input('references', []);
            
            foreach ($references as $ref) {
                // Only save if a number is actually provided
                if (!empty($ref['reference_number'])) {
                    $document->references()->create([
                        'reference_type'   => $ref['reference_type'] ?? 'Challan',
                        'reference_number' => $ref['reference_number'],
                        'reference_date'   => $ref['reference_date'] ?? null,
                    ]);
                }
            }

            $document->refresh();

            // 5. Evaluate checklist details
            try {
                $validation = $this->validationService->evaluateDocument($document);
            } catch (\Throwable $e) {
                throw new \Error(
                    "DEBUG CRASH: " . $e->getMessage() . 
                    " in " . $e->getFile() . " on line " . $e->getLine() . 
                    " | DB Snapshot: " . json_encode($document->compiled_profile_snapshot)
                );
            }

            if ($request->ajax()) {
                return response()->json([
                    'status'     => 'success',
                    'validation' => $validation
                ]);
            }

            return back()->with('success', 'Draft saved.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Generate the Pre-Posting Summary (AJAX) - Updated for Polymorphic References
     */
    public function preview(string $type, string $id)
    {
        $document = Document::with(['items', 'references'])->findOrFail($id);
        
        $totalQty = $document->items->sum('quantity');
        $totalValue = $document->items->sum(function($item) {
            return $item->quantity * ($item->unit_cost ?? 0);
        });

        // Map references into a clean string (e.g., "Challan: 123, Allocation: A-456")
        $refString = $document->references->map(function($r) {
            return $r->reference_type . ': ' . $r->reference_number;
        })->implode(' | ');

        return response()->json([
            'lines'       => $document->items->count(),
            'total_qty'   => $totalQty,
            'total_value' => number_format($totalValue, 2),
            'reference'   => $refString ?: 'None attached',
        ]);
    }

    /**
     * Unified AJAX Product Search for the Select2 spreadsheet grid.
     */
    public function searchProducts(Request $request)
    {
        $results = $this->productResolver->search($request->input('q', ''));
        
        $formatted = $results->map(function ($item) {
            return [
                'id'            => $item['type_raw'] . '_' . $item['id'], 
                'text'          => $item['name'] . ' (' . $item['type_label'] . ')',
                'current_stock' => $item['current_stock']
            ];
        });

        return response()->json(['results' => $formatted]);
    }



    /**
     * Generates official standard A4 printed copy of posted files.
     */
    public function print(string $type, string $id)
    {
        $document = Document::with(['items.stockable', 'timelines', 'creator'])->findOrFail($id);
        
        if ($document->status !== DocumentState::POSTED->value) {
            abort(403, 'Only finalized/posted documents can be printed officially.');
        }

        return view('storeops::operations.print', compact('document', 'type'));
    }

    /**
     * Fetches raw compiled profile rules for structural debugging.
     */
    public function productProfile(string $type, int $id)
    {
        try {
            $compiler = app(\GovStore\StoreOperations\Services\ProfileCompilerService::class);
            $normalizedType = strtolower(class_basename($type));
            $compiled = $compiler->compileItem($normalizedType, $id);

            return response()->json($compiled);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handles polymorphic file attachments uploader.
     */
    public function uploadAttachment(Request $request, string $type, string $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg,docx,xlsx|max:10240', 
            'category' => 'required|string'
        ]);

        $document = Document::findOrFail($id);

        try {
            if ($document->status !== DocumentState::DRAFT->value) {
                throw new Exception("Cannot attach files to a finalized document.");
            }

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $path = $file->store('attachments', 'public');

            $attachment = $document->attachments()->create([
                'file_path'     => $path,
                'original_name' => '[' . strtoupper($request->input('category')) . '] ' . $originalName,
                'mime_type'     => $file->getClientMimeType(),
                'uploaded_by'   => auth()->id() ?? 1,
            ]);

            return response()->json([
                'status' => 'success',
                'attachment' => [
                    'id'   => $attachment->id,
                    'name' => $attachment->original_name,
                    'url'  => Storage::url($attachment->file_path)
                ]
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Securely deletes physical files and dynamic database rows.
     */
    public function deleteAttachment(string $type, string $id, string $attachmentId)
    {
        $document = Document::findOrFail($id);

        try {
            if ($document->status !== DocumentState::DRAFT->value) {
                throw new Exception("Cannot alter a finalized document.");
            }

            $attachment = $document->attachments()->findOrFail($attachmentId);
            Storage::disk('public')->delete($attachment->file_path);
            $attachment->delete();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Dynamic AJAX metadata rendering engine.
     * Generates server-side HTML inputs natively from Capability Classes.
     */
    public function renderMeta(Request $request)
    {
        $productType = $request->input('product_type');
        $productId = $request->input('product_id');
        $quantity = (int) $request->input('quantity', 1);
        $rowIndex = $request->input('row_index', 0);
        $documentId = $request->input('document_id');

        // 1. Resolve compiled profile
        $compiler = app(\GovStore\StoreOperations\Services\ProfileCompilerService::class);
        $normalizedType = strtolower(class_basename($productType));
        $compiledRules = $compiler->compileItem($normalizedType, $productId);

        // 2. Load existing metadata values if we are editing an existing draft
        $item = null;
        if ($documentId) {
            $document = Document::find($documentId);
            if ($document) {
                $item = $document->items()
                    ->where('product_type', $normalizedType)
                    ->where('product_id', $productId)
                    ->first();
                
                if ($item) {
                    $item->quantity = $quantity; 
                }
            }
        }

        // 3. Loop through active capabilities and concatenate their pre-rendered Blade layouts
        $html = '';
        foreach ($compiledRules as $code => $meta) {
            if (isset($meta['enforced']) && $meta['enforced'] === true) {
                $capability = \GovStore\StoreOperations\Services\CapabilityRegistry::make($code);
                
                // Pass layout parameters down inside the config payload contextually
                $html .= $capability->renderUI($item, [
                    'config'    => $meta['config'] ?? [],
                    'row_index' => $rowIndex,
                    'quantity'  => $quantity
                ]);
            }
        }

        return response()->json([
            'html' => $html,
            'has_requirements' => !empty($html)
        ]);
    }
}