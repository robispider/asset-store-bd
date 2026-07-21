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
use Exception;

class DocumentWorkspaceController extends Controller
{
    protected ProductResolver $productResolver;
    protected GoodsReceiptService $receiptService;
    protected GoodsIssueService $issueService;
    protected PostingPipelineManager $pipelineManager;

    /**
     * Unified, single constructor injecting all required MVE Services.
     */
    public function __construct(
        ProductResolver $productResolver, 
        GoodsReceiptService $receiptService,
        GoodsIssueService $issueService,
        PostingPipelineManager $pipelineManager
    ) {
        $this->productResolver = $productResolver;
        $this->receiptService = $receiptService;
        $this->issueService = $issueService;
        $this->pipelineManager = $pipelineManager;
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

     // TEMPORARY DEBUG: Throw the exact payload reaching the server
    //    throw new \Exception("Raw Form Data: " . json_encode($request->all()));

        $document = Document::findOrFail($id);
        $headerData = $request->only(['reference_no', 'reference_date', 'purchase_type']);
        $rawLines = [];

        // Normalize raw grid lines payload
        foreach ($request->input('items', []) as $rowId => $item) {
            
            // Extract type and ID from the composed key (e.g. "consumable_3")
            if (isset($item['id']) && str_contains($item['id'], '_')) {
                [$rawType, $productId] = explode('_', $item['id']);
                $shortType = strtolower(class_basename($rawType));
            } else {
                // Fallback for initialization / empty rows
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

            // 2. Persist custom metadata fields (Serials, Expiries) dynamically to EAV table
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
                    $dbItem->metadata()->delete(); // Reset old draft data

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

            if ($request->ajax()) {
                return response()->json(['status' => 'success']);
            }
            return back()->with('success', 'Draft saved.');
        } catch (Exception $e) {
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
     * Finalize and execute the Composable Materialization Pipeline
     */
    public function post(Request $request, string $type, string $id)
    {
        $document = Document::findOrFail($id);

        try {
            // Auto-save any last minute grid/header adjustments first
            $this->saveDraft($request, $type, $id);

            // Execute the sequential transactional pipeline steps
            $this->pipelineManager->materialize($document, auth()->id());

            return redirect()->route('storeops.documents.workspace', ['type' => $type, 'id' => $id])
                             ->with('success', 'Document finalized. Inventory ledger and asset counts have materialized successfully.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
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
}