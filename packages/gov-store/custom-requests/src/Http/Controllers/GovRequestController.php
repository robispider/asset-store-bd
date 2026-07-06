<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Services\RequestService;
use Illuminate\Support\Facades\Log;

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
    public function catalog()
    {
        // 1. Get all consumables with remaining stock
        $consumables = \App\Models\Consumable::all()->filter(function ($item) {
            return $item->numRemaining() > 0;
        });

        // 2. Get all accessories with remaining stock
        $accessories = \App\Models\Accessory::all()->filter(function ($item) {
            return $item->numRemaining() > 0;
        });

        // 3. Get all assets that are marked "requestable" and are not currently checked out
        $assets = \App\Models\Asset::where('requestable', 1)
                                   ->whereNull('assigned_to')
                                   ->get();

        return view('govstore::catalog.index', compact('consumables', 'accessories', 'assets'));
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