<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Services\BasketService;
use App\Models\Location;

class BasketController extends Controller
{
    public function index(BasketService $service)
    {
        $basket = $service->getOrCreateDraftBasket(auth()->id());
        $basket->load(['items.requested']);
        $locations = Location::orderBy('name')->get();

        return view('govstore::basket.index', compact('basket', 'locations'));
    }

    public function add(Request $request, BasketService $service)
    {
        $request->validate([
            'item_type' => 'required|string',
            'item_id' => 'required|integer',
        ]);

        try {
            $basket = $service->addItem(auth()->id(), $request->item_type, $request->item_id);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Item added to your basket.',
                    'count' => $basket->items()->count()
                ]);
            }
            return redirect()->back()->with('success', 'Item added to your service request basket.');
        } catch (\Exception $e) {
            if ($request->ajax()) return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function updateQty(Request $request, BasketService $service)
    {
        try {
            $service->updateItemQty(auth()->id(), $request->item_id, (int)$request->qty);
            return redirect()->back()->with('success', 'Basket quantity updated.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function remove($itemId, BasketService $service)
    {
        $service->removeItem(auth()->id(), $itemId);
        return redirect()->back()->with('success', 'Item removed from basket.');
    }

 public function submit(Request $request, BasketService $service)
    {
        $request->validate([
            'request_type' => 'required|string',
            'purpose' => 'required|string|max:255',
            'justification' => 'required|string',
            'required_by_date' => 'nullable|date',
            'delivery_location_id' => 'nullable|integer',
        ]);

        try {
            // Submit and split the basket
            $requests = $service->submitBasket(auth()->id(), $request->all());
            
            // Extract the generated request numbers
            $numbers = collect($requests)->pluck('request_number')->join(', ');
            
            return redirect()->route('gov.requests.user.index')
                             ->with('success', "Service Request(s) [{$numbers}] submitted successfully!");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}