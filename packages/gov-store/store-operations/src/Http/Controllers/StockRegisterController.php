<?php

namespace GovStore\StoreOperations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use GovStore\StoreOperations\Services\InventoryLedgerService;
use App\Models\Consumable;
use App\Models\Accessory;
use App\Models\Component;

class StockRegisterController extends Controller
{
    protected InventoryLedgerService $ledgerService;

    public function __construct(InventoryLedgerService $ledgerService)
    {
        $this->ledgerService = $ledgerService;
    }

    /**
     * Dashboard listing all items in the storekeeper's warehouse
     */
    public function index()
    {
        $consumables = Consumable::with('category')->get();
        $accessories = Accessory::with('category')->get();
        $components  = Component::with('category')->get();

        return view('storeops::register.index', compact('consumables', 'accessories', 'components'));
    }

    /**
     * Displays the Immutable Stock Card (Kardex) for a specific item (Strictly Read-Only)
     */
    public function kardex(Request $request, $type, $id)
    {
        $modelClass = match (strtolower($type)) {
            'consumable' => Consumable::class,
            'accessory'  => Accessory::class,
            'component'  => Component::class,
            default      => abort(404, __('storeops::storeops.invalid_stockable_type'))
        };

        $item = $modelClass::findOrFail($id);

        // Fetch pre-computed movements from the service layer
        $movements = $this->ledgerService->getKardexFor($modelClass, $id);

        // Pure, elegant HTTP Content Negotiation - No custom query parameter parameters needed
        if ($request->ajax()) {
            return view('storeops::register.kardex-table', compact('movements'));
        }

        return view('storeops::register.kardex', compact('item', 'movements', 'type'));
    }
}
