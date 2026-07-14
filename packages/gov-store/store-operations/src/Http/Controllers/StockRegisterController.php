<?php

namespace GovStore\StoreOperations\Http\Controllers;

use Illuminate\Routing\Controller;
use GovStore\StoreOperations\Models\InventoryMovement;
use App\Models\Consumable;
use App\Models\Accessory;
use App\Models\Component;

class StockRegisterController extends Controller
{
    /**
     * Dashboard listing all items in the storekeeper's warehouse
     */
    public function index()
    {
        // Globally scoped: MinistryLocationScope applies automatically
        $consumables = Consumable::with('category')->get();
        $accessories = Accessory::with('category')->get();
        $components  = Component::with('category')->get();

        return view('storeops::register.index', compact('consumables', 'accessories', 'components'));
    }

    /**
     * Displays the Immutable Stock Card (Kardex) for a specific item
     */
    public function kardex($type, $id)
    {
        // Resolve model class safely
        $modelClass = match (strtolower($type)) {
            'consumable' => Consumable::class,
            'accessory' => Accessory::class,
            'component' => Component::class,
            default => abort(404, 'Invalid stockable type')
        };

        $item = $modelClass::findOrFail($id);

        // Fetch movements, ordered chronologically
        $movements = InventoryMovement::with('document', 'creator')
            ->where('stockable_type', $modelClass)
            ->where('stockable_id', $id)
            ->orderBy('created_at', 'asc')
            ->get();

        // Calculate running balance in memory for display
        $runningBalance = 0;
        foreach ($movements as $movement) {
            if ($movement->movement_type === 'IN') {
                $runningBalance += $movement->quantity;
            } else {
                $runningBalance -= $movement->quantity;
            }
            $movement->running_balance = $runningBalance;
        }

        return view('storeops::register.kardex', compact('item', 'movements', 'type'));
    }
}
