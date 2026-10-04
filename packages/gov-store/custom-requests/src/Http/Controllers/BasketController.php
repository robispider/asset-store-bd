<?php

namespace GovStore\CustomRequests\Http\Controllers;

use App\Models\Location;
use Exception;
use GovStore\CustomRequests\Services\BasketService;
use GovStore\TenantScope\Services\ActionFailure;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

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
            'qty' => 'nullable|integer|min:1', // Added validation
        ]);

        try {
            // Normalize PascalCase (e.g. 'Consumable') to standard lowercase morph key ('consumable')
            $normalizedType = strtolower(class_basename($request->item_type));

            // Capture selected quantity (Defaults to 1 for Assets or legacy buttons)
            $qty = (int) $request->input('qty', 1);

            $basket = $service->addItem(auth()->id(), $normalizedType, $request->item_id, $qty);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => __('requestlabels::requests.basketcontroller_flash_item_added_ajax'),
                    'count' => $basket->items()->count(),
                ]);
            }

            return redirect()->back()->with('success', __('requestlabels::requests.basketcontroller_flash_item_added'));
        } catch (Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => app(ActionFailure::class)->message($e)], 400);
            }

            return redirect()->back()->with('error', app(ActionFailure::class)->message($e));
        }
    }

    /**
     * Update the quantity of an item in the basket.
     * Supports both standard redirect and background AJAX auto-saves.
     */
    public function updateQty(Request $request, BasketService $service)
    {
        try {
            $service->updateItemQty(auth()->id(), $request->item_id, (int) $request->qty);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true]);
            }

            return redirect()->back()->with('success', __('requestlabels::requests.basketcontroller_flash_qty_updated'));
        } catch (Exception $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => app(ActionFailure::class)->message($e)], 400);
            }

            return redirect()->back()->with('error', app(ActionFailure::class)->message($e));
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
            return redirect()->back()->with('error', app(ActionFailure::class)->message($e));
        }
    }
}
