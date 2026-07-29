<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Supplier;
use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TrackingRetrospectiveController extends Controller
{
    public function index(TrackingReference $reference, Request $request)
    {
        $categories = Category::all();
        $manufacturers = Manufacturer::all();
        $suppliers = Supplier::all();

        $assets = collect();
        $searched = false;

        if ($request->filled('search_trigger')) {
            $searched = true;
            $query = Asset::with(['model.category', 'model.manufacturer', 'supplier']);

            // 1. Filter by Category
            if ($request->filled('category_id')) {
                $query->whereHas('model', function ($q) use ($request) {
                    $q->where('category_id', $request->input('category_id'));
                });
            }

            // 2. Filter by Manufacturer
            if ($request->filled('manufacturer_id')) {
                $query->whereHas('model', function ($q) use ($request) {
                    $q->where('manufacturer_id', $request->input('manufacturer_id'));
                });
            }

            // 3. Filter by Supplier
            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->input('supplier_id'));
            }

            // 4. Filter by Purchase Dates
            if ($request->filled('purchase_start')) {
                $query->where('purchase_date', '>=', $request->input('purchase_start'));
            }
            if ($request->filled('purchase_end')) {
                $query->where('purchase_date', '<=', $request->input('purchase_end'));
            }

            $assets = $query->limit(500)->get();

            // Resolve existing active program associations for visual badges
            $allAssociatedAssetIds = TrackingAssociation::where('associatable_type', Asset::class)
                ->where('status', 'ACTIVE')
                ->get()
                ->groupBy('associatable_id');

            foreach ($assets as $asset) {
                $asset->existing_associations = $allAssociatedAssetIds->get($asset->id) ?? collect();
            }
        }

        return view('govtracking::references.retrospective', compact(
            'reference',
            'categories',
            'manufacturers',
            'suppliers',
            'assets',
            'searched'
        ));
    }

    public function associate(Request $request, TrackingReference $reference)
    {
        $request->validate([
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'exists:hardware,id', // 'hardware' is Snipe-IT's physical assets table
        ]);

        $assetIds = $request->input('asset_ids');
        $tagCount = 0;

        DB::transaction(function () use ($reference, $assetIds, &$tagCount) {
            foreach ($assetIds as $id) {
                // Ensure duplicate combinations are bypassed gracefully
                $assoc = TrackingAssociation::firstOrCreate([
                    'tracking_reference_id' => $reference->id,
                    'associatable_type' => Asset::class,
                    'associatable_id' => $id,
                ]);

                if ($assoc->wasRecentlyCreated) {
                    $tagCount++;
                } else if ($assoc->status !== 'ACTIVE') {
                    $assoc->update(['status' => 'ACTIVE']);
                    $tagCount++;
                }
            }

            // Record execution to the audit log
            if ($tagCount > 0) {
                TrackingTimeline::create([
                    'tracking_reference_id' => $reference->id,
                    'event_type' => 'RETROSPECTIVE_TAGGING',
                    'description' => "Linked {$tagCount} legacy inventory items to reference code via bulk retrospective matching operations.",
                    'actor_id' => Auth::id(),
                    'metadata' => [
                        'bulk_count' => $tagCount,
                        'processed_asset_sample' => array_slice($assetIds, 0, 5)
                    ],
                    'occurred_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('gov.tracking.references.dashboard', $reference->id)
            ->with('success', "Associated {$tagCount} legacy assets successfully.");
    }
}