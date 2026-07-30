<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Supplier;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TrackingRetrospectiveController extends Controller
{
    public function index(Initiative $initiative, Request $request)
    {
        $categories = Category::all();
        $manufacturers = Manufacturer::all();
        $suppliers = Supplier::all();
        $trackingCodes = TrackingCode::where('initiative_id', $initiative->id)->get();

        $assets = collect();
        $searched = false;

        if ($request->filled('search_trigger')) {
            $searched = true;
            $query = Asset::with(['model.category', 'model.manufacturer', 'supplier']);

            if ($request->filled('category_id')) {
                $query->whereHas('model', function ($q) use ($request) {
                    $q->where('category_id', $request->input('category_id'));
                });
            }

            if ($request->filled('manufacturer_id')) {
                $query->whereHas('model', function ($q) use ($request) {
                    $q->where('manufacturer_id', $request->input('manufacturer_id'));
                });
            }

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->input('supplier_id'));
            }

            if ($request->filled('purchase_start')) {
                $query->where('purchase_date', '>=', $request->input('purchase_start'));
            }
            
            if ($request->filled('purchase_end')) {
                $query->where('purchase_date', '<=', $request->input('purchase_end'));
            }

            $assets = $query->limit(500)->get();

            // Fetch existing associations to prevent double-tagging
            $existingAssocIds = TrackingAssociation::where('associatable_type', Asset::class)
                ->whereIn('tracking_code_id', $trackingCodes->pluck('id'))
                ->where('status', 'ACTIVE')
                ->pluck('associatable_id')
                ->toArray();

            foreach ($assets as $asset) {
                $asset->is_already_tagged = in_array($asset->id, $existingAssocIds);
            }
        }

        return view('govtracking::retrospective.index', compact(
            'initiative', 'trackingCodes', 'categories', 'manufacturers', 'suppliers', 'assets', 'searched'
        ));
    }

    public function associate(Request $request, Initiative $initiative)
    {
        $request->validate([
            'tracking_code_id' => 'required|exists:gov_tracking_codes,id',
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'exists:hardware,id',
        ]);

        $trackingCode = TrackingCode::findOrFail($request->input('tracking_code_id'));

        // Ensure the selected tracking code actually belongs to this initiative umbrella
        if ($trackingCode->initiative_id !== $initiative->id) {
            abort(403, 'Invalid tracking code association.');
        }

        $assetIds = $request->input('asset_ids');
        $tagCount = count($assetIds);
        $now = now();
        
        DB::transaction(function () use ($initiative, $trackingCode, $assetIds, $tagCount, $now) {
            $associationData = [];
            foreach ($assetIds as $id) {
                $associationData[] = [
                    'tracking_code_id'  => $trackingCode->id,
                    'associatable_type' => Asset::class,
                    'associatable_id'   => $id,
                    'status'            => 'ACTIVE',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            TrackingAssociation::insertOrIgnore($associationData);

            TrackingTimeline::create([
                'initiative_id' => $initiative->id,
                'event_type'    => 'RETROSPECTIVE_TAGGING',
                'description'   => "Assigned {$tagCount} historical assets to tracking code '{$trackingCode->tracking_code}' via bulk retrospective mapping.",
                'actor_id'      => Auth::id(),
                'metadata'      => ['tracking_code' => $trackingCode->tracking_code, 'bulk_count' => $tagCount],
                'occurred_at'   => $now,
            ]);
        });

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', "Successfully linked {$tagCount} historical assets to the initiative.");
    }
}