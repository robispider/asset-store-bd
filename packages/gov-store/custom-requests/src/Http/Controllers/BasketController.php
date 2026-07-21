<?php

namespace GovStore\CustomRequests\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\CustomRequests\Services\BasketService;
use App\Models\Location;
use Exception;

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
            // Normalize PascalCase (e.g. 'Consumable') to standard lowercase morph key ('consumable')
            $normalizedType = strtolower(class_basename($request->item_type));

            $basket = $service->addItem(auth()->id(), $normalizedType, $request->item_id);
            
            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => __('requestlabels::requests.basketcontroller_flash_item_added_ajax'),
                    'count' => $basket->items()->count()
                ]);
            }
            return redirect()->back()->with('success', __('requestlabels::requests.basketcontroller_flash_item_added'));
        } catch (Exception $e) {
            if ($request->ajax()) return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Fixed: Added missing catch block to resolve fatal compilation exception
     */
    public function updateQty(Request $request, BasketService $service)
    {
        try {
            $service->updateItemQty(auth()->id(), $request->item_id, (int)$request->qty);
            return redirect()->back()->with('success', __('requestlabels::requests.basketcontroller_flash_qty_updated'));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function remove($itemId, BasketService $service)
    {
        $service->removeItem(auth()->id(), $itemId);
        return redirect()->back()->with('success', __('requestlabels::requests.basketcontroller_flash_item_removed'));
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
                             ->with('success', __('requestlabels::requests.basketcontroller_flash_request_submitted', ['numbers' => $numbers]));
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}