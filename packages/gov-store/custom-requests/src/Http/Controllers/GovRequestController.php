<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Services\RequestService;
use Illuminate\Support\Facades\Log;
 use GovStore\CustomRequests\Services\CatalogService;

class GovRequestController extends Controller
{
    public function index()
    {
        // Fetch only requests made by the currently logged-in user
        $requests = \GovStore\CustomRequests\Models\ItemRequest::with('requestable')
                        ->where('requested_by', auth()->id())
                        ->orderBy('created_at', 'desc')
                        ->get();

        return view('govstore::user.index', compact('requests'));
    }
   public function catalog(CatalogService $catalogService)
    {
        $userId = auth()->id();

        // 1. Get the unified list of products
        $catalogItems = $catalogService->getUnifiedCatalog();

        // 2. Fetch the user's request counts for their personalized pipeline
        $pendingCount  = \GovStore\CustomRequests\Models\ItemRequest::where('requested_by', $userId)->where('status', 'pending')->count();
        $approvedCount = \GovStore\CustomRequests\Models\ItemRequest::where('requested_by', $userId)->where('status', 'approved')->count();
        $rejectedCount = \GovStore\CustomRequests\Models\ItemRequest::where('requested_by', $userId)->where('status', 'rejected')->count();

        // 3. Return the single unified dashboard view
        return view('govstore::catalog.index', compact(
            'catalogItems', 
            'pendingCount', 
            'approvedCount', 
            'rejectedCount'
        ));
    }
    public function store(Request $request, RequestService $service)
    {
        // Validate incoming form data
        $request->validate([
            'item_type' => 'required|string', // e.g. 'Consumable', 'Asset', 'Accessory'
            'item_id'   => 'required|integer',
            'notes'     => 'nullable|string'
        ]);

        // Convert the simple string (e.g., 'Consumable') into the full Snipe-IT Model class path
        $modelClass = 'App\\Models\\' . ucfirst(strtolower($request->item_type));

        try {
            $service->submitRequest($modelClass, $request->item_id, auth()->user(), $request->notes);
            
            // Redirect back to the item page with a Snipe-IT success banner
            return redirect()->back()->with('success', 'Item request submitted successfully and is pending approval.');
        } catch (\Exception $e) {
            Log::error("Request Submit Error: " . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}