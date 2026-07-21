<?php

namespace GovStore\StoreOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GovStore\StoreOperations\Models\GoodsReceipt;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use Illuminate\Support\Facades\DB;
use Exception;

class GoodsReceiptController extends Controller
{
    protected GoodsReceiptService $receiptService;

    public function __construct(GoodsReceiptService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    /**
     * Show the Goods Receipt Creation Workspace
     */
    public function create()
    {
        // In Phase 4, this will be handled by Livewire.
        // For now, we return the blade view.
        return view('storeops::receipts.create');
    }

    /**
     * Save the document as a Draft (Does NOT update inventory)
     */
    public function store(Request $request)
    {
        $request->validate([
            'purchase_type' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|string',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        try {
            $headerData = $request->only([
                'purchase_type', 'reference_no', 'reference_date', 
                'received_by_type', 'committee_ref', 'supplier_id'
            ]);

            $receipt = $this->receiptService->saveDraft(
                $headerData, 
                $request->input('items'), 
                auth()->id()
            );

            return redirect()->route('storeops.receipts.show', $receipt->id)
                ->with('success', 'Draft saved successfully. You can review and attach files before posting.');

        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Post the document to the Ledger (Finalize)
     */
    public function submit(Request $request, $id)
    {
        $receipt = GoodsReceipt::findOrFail($id);

        try {
            $this->receiptService->post($receipt, auth()->id());

            return redirect()->route('storeops.receipts.show', $receipt->id)
                ->with('success', 'Goods Receipt successfully posted to the inventory ledger.');

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}