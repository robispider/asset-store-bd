<?php

namespace GovStore\StoreOperations\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\StoreOperations\Models\GoodsReceipt;
use GovStore\StoreOperations\Models\GoodsReceiptItem;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\StoreOperations\Services\DocumentNumberService;
use GovStore\TenantScope\Contexts\TenantContext;
use App\Models\Consumable;
use Illuminate\Support\Facades\DB;
use Exception;

class GoodsReceiptController extends Controller
{
    public function create()
    {
        // For MVP, we will load consumables for the dropdown. 
        // In the future, this can be an AJAX select2.
        $stockables = Consumable::all(); 
        
        return view('storeops::receipts.create', compact('stockables'));
    }

    public function store(Request $request, DocumentNumberService $docService, TenantContext $context)
    {
        $request->validate([
            'purchase_type' => 'required|string',
            'reference_no' => 'required|string',
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($request, $docService, $context) {
                // 1. Create Header
                $receipt = GoodsReceipt::create([
                    'receipt_no' => $docService->generate('GR', 'gov_goods_receipts', 'receipt_no'),
                    'purchase_type' => $request->purchase_type,
                    'reference_no' => $request->reference_no,
                    'received_by_type' => 'SELF',
                    'status' => 'DRAFT',
                    'company_id' => $context->companyId,
                    'location_id' => $context->locationId,
                    'created_by' => auth()->id(),
                ]);

                // 2. Create Lines
                foreach ($request->items as $item) {
                    GoodsReceiptItem::create([
                        'goods_receipt_id' => $receipt->id,
                        'stockable_type' => Consumable::class, // Hardcoded for Phase 3 MVP test
                        'stockable_id' => $item['id'],
                        'quantity' => $item['qty'],
                    ]);
                }

                // 3. Auto-Submit for the vertical slice test
                app(GoodsReceiptService::class)->submit($receipt);
            });

            return redirect()->back()->with('success', 'Goods Receipt submitted and inventory projected successfully!');
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
