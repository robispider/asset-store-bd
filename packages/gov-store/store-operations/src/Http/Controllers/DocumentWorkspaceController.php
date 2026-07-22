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

    // Inject DocumentValidationService in constructor
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


    public function post(Request $request, string $type, string $id)
    {
        $document = Document::findOrFail($id);

        try {
            // 1. Auto-save the latest grid values to the draft
            $this->saveDraft($request, $type, $id);
            $document->refresh();

            // --- 2. FATAL DEBUGGER BYPASS IN POSTING ---
            try {
                // FIXED: Changed $this->validator to $this->validationService
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

                // Execute the materialization pipeline (Kardex ledger and assets)
                $this->pipelineManager->materialize($document, auth()->id());

            } catch (\Throwable $e) {
                // FORCE CRASH TO RED IGNITION SCREEN (Keep for safety until you confirm post is successful)
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
     * The Operational Hub (Document Listings Dashboard)
     */
    public function hub(Request $request)
    {
        // Loads any document class (Receipt, Issue) uniformly
        $documents = Document::with('creator')->orderBy('created_at', 'desc')->paginate(20);
        return view('storeops::operations.hub', compact('documents'));
    }

    /**
     * Instantly initialize a blank DRAFT document of a specific type (Receipt, Issue).
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
     * The Unified Workspace Shell (Loads both editors and view-only archives)
     */
public function workspace(string $type, string $id)
    {
        // Change items.stockable to items.product
        $document = Document::with(['items.product', 'items.metadata', 'timelines', 'creator'])->findOrFail($id);
        
        return view('storeops::operations.workspace', compact('document', 'type'));
    }

   /**
     * Save the Document Draft (Consolidates composed type_id keys on save)
     */
     public function saveDraft(Request $request, string $type, string $id)
    {
        $document = Document::findOrFail($id);
        $headerData = $request->only(['reference_no', 'reference_date', 'purchase_type']);
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
            // 1. Delegate base save and compile frozen snapshot
            match($type) {
                'receipt' => $this->receiptService->saveDraft($headerData, $rawLines, auth()->id(), $document),
                'issue'   => $this->issueService->saveDraft($headerData, $rawLines, auth()->id(), $document),
            };

            // 2. Persist custom metadata fields (Serials, Expiries)
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
                            $dbItem->metadata()->create([
                                'field_key' => $fieldKey,
                                'value'     => $value,
                                'row_index' => $rowIndex
                            ]);
                        }
                    }
                }
            }

            $document->refresh();

            // --- 3. FATAL DEBUGGER BYPASS (FORCES RED DEBUG PAGE TO OPEN) ---
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
     * Unified AJAX Product Search for the Spreadsheet Grid (Outputs Composed IDs)
     */
    public function searchProducts(Request $request)
    {
        $results = $this->productResolver->search($request->input('q', ''));
        
        // Map to Select2 format with composed ID
        $formatted = $results->map(function ($item) {
            return [
                'id'            => $item['type_raw'] . '_' . $item['id'], // e.g. "App\Models\Consumable_3"
                'text'          => $item['name'] . ' (' . $item['type_label'] . ')',
                'current_stock' => $item['current_stock']
            ];
        });

        return response()->json(['results' => $formatted]);
    }

    /**
     * Generate the Pre-Posting Summary (AJAX)
     */
    public function preview(string $type, string $id)
    {
        $document = Document::with('items')->findOrFail($id);
        
        $totalQty = $document->items->sum('quantity');
        $totalValue = $document->items->sum(function($item) {
            return $item->quantity * ($item->unit_cost ?? 0);
        });

        return response()->json([
            'lines'       => $document->items->count(),
            'total_qty'   => $totalQty,
            'total_value' => number_format($totalValue, 2),
            'reference'   => $document->reference_no ?? 'None attached',
        ]);
    }

    

    /**
     * Generate standard, high-fidelity government A4 PDF printout
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
     * Dedicated endpoint to fetch compiled capabilities and requirements for a single item.
     */
    public function productProfile(string $type, int $id)
    {
        try {
            $compiler = app(\GovStore\StoreOperations\Services\ProfileCompilerService::class);
            
            // Normalize the polymorphic type name (handles full namespace strings seamlessly)
            $normalizedType = strtolower(class_basename($type));

            // Compile the recursive capability profile
            $compiled = $compiler->compileItem($normalizedType, $id);

            return response()->json($compiled);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

      /**
     * Handle AJAX File Upload and associate it with the Document polymorphically.
     */
    public function uploadAttachment(Request $request, string $type, string $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg,docx,xlsx|max:10240', // Max 10MB
            'category' => 'required|string'
        ]);

        $document = Document::findOrFail($id);

        try {
            if ($document->status !== DocumentState::DRAFT->value) {
                throw new Exception("Cannot attach files to a finalized document.");
            }

            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            
            // Store file securely inside storage/app/public/attachments/
            $path = $file->store('attachments', 'public');

            // Persist polymorphic entry
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
     * Delete physical file and database entry.
     */
    public function deleteAttachment(string $type, string $id, string $attachmentId)
    {
        $document = Document::findOrFail($id);

        try {
            if ($document->status !== DocumentState::DRAFT->value) {
                throw new Exception("Cannot alter a finalized document.");
            }

            $attachment = $document->attachments()->findOrFail($attachmentId);

            // Delete physical file
            Storage::disk('public')->delete($attachment->file_path);
            
            // Delete database row
            $attachment->delete();

            return response()->json(['status' => 'success']);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}