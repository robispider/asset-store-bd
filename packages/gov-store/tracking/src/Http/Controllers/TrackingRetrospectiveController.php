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
        // Enforce: Monitors are read-only and blocked from retrospective tag searches
        $this->authorizeManagement($initiative); 

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
        $this->authorizeManagement($initiative); 

        $assetsTable = (new Asset)->getTable();

        $request->validate([
            'tracking_code_id' => 'required|exists:gov_tracking_codes,id',
            'asset_ids' => 'required|array',
            'asset_ids.*' => "exists:{$assetsTable},id",
        ]);

        $trackingCode = TrackingCode::findOrFail($request->input('tracking_code_id'));

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
                    'category_id'       => $trackingCode->targets->first()->category_id ?? 0, // safe-default
                    'location_id'       => $trackingCode->initiative->manager_location_id ?? 0,
                    'quantity'          => 1,
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

    /**
     * Protect routes with the formalized GovStore Operation Unit permissions.
     * Allows HEAD, OFFICER, and SUPPORT staff to run bulk retrospect tagging.
     */
    protected function authorizeManagement(Initiative $initiative, array $allowedDesignations = ['HEAD', 'OFFICER', 'SUPPORT'])
    {
        $user = auth()->user();
        if (!$user) abort(403);

        if ($user->isSuperUser()) return; 

        $isCompanyAdmin = $user->company_id === $initiative->owner_company_id && 
            \GovStore\Organization\Models\CompanyAdmin::where('user_id', $user->id)->exists();
        
        $isOperationUnitManager = \GovStore\Tracking\Models\OperationUnit::where('initiative_id', $initiative->id)
            ->where('user_id', $user->id)
            ->whereIn('designation', $allowedDesignations)
            ->exists();

        if (!$isCompanyAdmin && !$isOperationUnitManager) {
            abort(403, 'Unauthorized. Only members of the Initiative Management Team or Ministry Admins can execute configurations.');
        }
    }
}