<?php

namespace GovStore\StoreOperations\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\StoreOperations\Models\GoodsIssueItem;
use GovStore\StoreOperations\Services\GoodsIssueService;
use GovStore\StoreOperations\Services\DocumentNumberService;
use GovStore\TenantScope\Contexts\TenantContext;
use App\Models\Consumable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class GoodsIssueController extends Controller
{
    public function create(TenantContext $context)
    {
        // For MVP, filter users and items by the active Tenant Context Location
        $users = User::where('location_id', $context->locationId)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get();
            
        // Only show items that actually have stock > 0 to prevent UI clutter
        $stockables = Consumable::where('qty', '>', 0)->get(); 
        
        return view('storeops::issues.create', compact('stockables', 'users'));
    }

    public function store(Request $request, DocumentNumberService $docService, TenantContext $context, GoodsIssueService $issueService)
    {
        $request->validate([
            'issue_type' => 'required|string',
            'issued_to_id' => 'required_if:issue_type,TO_USER|nullable|integer',
            'items' => 'required|array',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($request, $docService, $context, $issueService) {
                // 1. Create Header
                $issue = GoodsIssue::create([
                    'issue_no' => $docService->generate('GI', 'gov_goods_issues', 'issue_no'),
                    'issue_type' => $request->issue_type,
                    'issued_to_id' => $request->issued_to_id,
                    'status' => 'DRAFT',
                    'company_id' => $context->companyId,
                    'location_id' => $context->locationId,
                    'created_by' => auth()->id(),
                ]);

                // 2. Create Lines
                foreach ($request->items as $item) {
                    GoodsIssueItem::create([
                        'goods_issue_id' => $issue->id,
                        'stockable_type' => Consumable::class, // Hardcoded to Consumable for Phase 4 MVP
                        'stockable_id' => $item['id'],
                        'quantity' => $item['qty'],
                    ]);
                }

                // 3. Process the validation and generate ledger movements
                $issueService->submit($issue);
            });

            return redirect()->back()->with('success', __('storeops::storeops.success_goods_issued'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
