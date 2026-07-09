<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use GovStore\CustomRequests\Services\CatalogService;

class GovRequestController extends Controller
{
    public function index()
    {
        // Fetch only submitted requests made by the currently logged-in user (excluding active drafts)
        $requests = \GovStore\CustomRequests\Models\Request::with(['items.requested'])
                        ->where('requested_by', auth()->id())
                        ->where('approval_status', '!=', 'draft') // Hide drafts from their history list
                        ->orderBy('created_at', 'desc')
                        ->get();

        return view('govstore::user.index', compact('requests'));
    }

    public function catalog(CatalogService $catalogService)
    {
        $userId = auth()->id();

        // 1. Get the unified list of products
        $catalogItems = $catalogService->getUnifiedCatalog();

        // 2. Fetch the user's request counts using the correct 'approval_status' column
        // (Drafts are excluded; we count submitted, approved/in-progress, and rejected)
        $pendingCount  = \GovStore\CustomRequests\Models\Request::where('requested_by', $userId)
                            ->whereIn('approval_status', ['submitted', 'under_review'])
                            ->count();

        $approvedCount = \GovStore\CustomRequests\Models\Request::where('requested_by', $userId)
                            ->whereIn('approval_status', ['approved', 'partially_approved'])
                            ->count();

        $rejectedCount = \GovStore\CustomRequests\Models\Request::where('requested_by', $userId)
                            ->where('approval_status', 'rejected')
                            ->count();

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

    public function search(Request $request)
    {
        $term = $request->input('q', '');
        $type = strtolower($request->input('type', ''));

        if (empty($term) || empty($type)) {
            return response()->json([]);
        }

        $results = [];

        // Query Snipe-IT's core tables directly with optimized limits
        if ($type === 'consumable') {
            $items = \App\Models\Consumable::where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => $item->name . ' (Stock: ' . $item->numRemaining() . ')'];
            }
        } elseif ($type === 'accessory') {
            $items = \App\Models\Accessory::where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => $item->name . ' (Stock: ' . $item->numRemaining() . ')'];
            }
        } elseif ($type === 'asset') {
            $items = \App\Models\Asset::where('requestable', 1)
                ->whereNull('assigned_to')
                ->where(function($q) use ($term) {
                    $q->where('asset_tag', 'like', "%{$term}%")
                      ->orWhere('name', 'like', "%{$term}%");
                })
                ->limit(15)
                ->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => ($item->present()->name() ?: $item->asset_tag)];
            }
        }

        return response()->json($results);
    }
}
