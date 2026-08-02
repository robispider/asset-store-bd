<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\FundingType;
use GovStore\Tracking\Services\TrackingAuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // FIXED: Imported the central Log facade
use Illuminate\Validation\ValidationException;

class TrackingCodeController extends Controller
{
    protected TrackingAuthorizationService $authService;

    public function __construct(TrackingAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    public function create(Initiative $initiative)
    {
        $categories = Category::all();
        $fundingTypes = FundingType::where('primary_type', $initiative->primary_funding)->get();
        $geoAreas = class_exists(GeoArea::class) ? GeoArea::all() : collect();

        return view('govtracking::tracking_codes.create', compact('initiative', 'categories', 'fundingTypes', 'geoAreas'));
    }

   public function edit(Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        $categories = Category::all();
        $fundingTypes = FundingType::where('primary_type', $initiative->primary_funding)->get();
        $geoAreas = class_exists(GeoArea::class) ? GeoArea::all() : collect();

        $activeGeo = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
        $activePart = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();

        // FIXED (TenantScope Bypass): We eager load the location relation with withoutGlobalScopes()
        // to prevent localized tenant scopes from returning nulls on different branch office rows!
        $trackingCode->load([
            'targets.allocations.location' => function($query) {
                $query->withoutGlobalScopes();
            }
        ]);

        // Compile saved matrix cells for automatic front-end spreadsheet pre-population
        $savedMatrixValues = [];
        foreach ($trackingCode->targets as $target) {
            foreach ($target->allocations as $alloc) {
                $savedMatrixValues[$alloc->location_id][$target->category_id] = $alloc->allocated_qty;
            }
        }

        return view('govtracking::tracking_codes.edit', compact(
            'initiative', 'trackingCode', 'categories', 'fundingTypes', 'geoAreas', 'activeGeo', 'activePart', 'savedMatrixValues'
        ));
    }

    /**
     * Dynamic, dual-axis office search.
     * Combines both Geographical Coverage and Organizational visibility rules.
     * Fully safe-guarded: catches all errors and logs them securely.
     */
    public function searchOffices(Request $request)
    {
        try {
            $request->validate([
                'initiative_id'        => 'required|exists:gov_initiatives,id',
                'geo_override'         => 'required|in:Inherit,GeoArea',
                'geo_area_id'          => 'required_if:geo_override,GeoArea',
                'participant_override' => 'required|in:Inherit,CrossTenant',
                'q'                    => 'nullable|string',
            ]);

            $term = $request->input('q');
            $initiative = Initiative::findOrFail($request->input('initiative_id'));

            // Start with a clean query bypassing local user TenantScope blockades
            $query = Location::withoutGlobalScopes();

            // 1. FILTER DIMENSION A: Participants (Organizational)
            if ($request->input('participant_override') === 'Inherit') {
                $query->where('company_id', $initiative->owner_company_id);
            }

            // 2. FILTER DIMENSION B: Geographical (Spatial)
            if ($request->input('geo_override') === 'GeoArea' && $request->filled('geo_area_id')) {
                $geoArea = GeoArea::find($request->input('geo_area_id'));
                
                if ($geoArea) {
                    // Resolve database tables dynamically to bypass table prefix constraints
                    $profileTable = (new \GovStore\Organization\Models\LocationProfile)->getTable();
                    $geoTable = (new \GovStore\GeoAreas\Models\GeoArea)->getTable();

                    $query->whereIn('id', function($subQuery) use ($geoArea, $profileTable, $geoTable) {
                        $subQuery->select('location_id')
                            ->from($profileTable)
                            ->join($geoTable, $profileTable . '.geo_area_id', '=', $geoTable . '.GeoAreaId')
                            ->where($geoTable . '.hid', 'LIKE', $geoArea->hid . '%');
                    });
                }
            }

            // 3. APPLY LIVE SEARCH TERM
            if (!empty($term)) {
                $query->where('name', 'LIKE', "%{$term}%");
            }

            $locations = $query->limit(20)->get();

            $results = $locations->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'text' => $loc->name
                ];
            });

            return response()->json(['results' => $results]);

        } catch (\Exception $e) {
            // Log to laravel.log automatically (Now unblocked by the import)
            Log::error('GovStore: searchOffices API Failure: ' . $e->getMessage(), [
                'exception' => $e,
                'request'   => $request->all()
            ]);

            // Return the exception message to your browser's Developer Tools Response panel
            return response()->json(['error' => 'Internal Server Error. ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request, Initiative $initiative)
    {
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        Log::info('GovStore: Incoming Tracking Code Save Payload', $request->all());

        $rules = [
            'tracking_code'     => 'required|string|unique:gov_tracking_codes,tracking_code|max:100',
            'task_title'        => 'required|string|max:255',
            'order_pdf'         => $initiative->require_documents ? 'required|file|mimes:pdf|max:10240' : 'nullable|file|mimes:pdf|max:10240',
            'fiscal_year'       => 'required|string|max:20',
            'funding_type_id'   => 'required|exists:gov_funding_types,id',
            'specificity_level' => 'required|in:1_BLANKET,2_CATEGORY,3_MATRIX',
        ];

        $specificity = $request->input('specificity_level');

        if ($specificity === '2_CATEGORY' || $specificity === '3_MATRIX' || $specificity === '1_BLANKET') {
            $rules['geo_override']          = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id']           = 'required_if:geo_override,GeoArea';
            $rules['participant_override']  = 'required|in:Inherit,CrossTenant';
        }

        $request->validate($rules);

        $selectedFund = FundingType::findOrFail($request->input('funding_type_id'));
        if ($selectedFund->primary_type !== $initiative->primary_funding) {
            throw ValidationException::withMessages(['funding_type_id' => 'Selected funding source does not match this Initiative\'s budget segment.']);
        }

        if ($specificity === '2_CATEGORY') {
            $categoryIds = array_column($request->input('targets'), 'category_id');
            if (count($categoryIds) !== count(array_unique($categoryIds))) {
                throw ValidationException::withMessages(['targets' => 'Duplicate categories are not allowed in the same Tracking Code.']);
            }
        }

        DB::transaction(function () use ($request, $initiative, $specificity) {
            $pdfPath = null;
            if ($request->hasFile('order_pdf')) {
                $pdfPath = $request->file('order_pdf')->store('tracking-orders/' . $initiative->id, 'local');
            }

            $trackingCode = $initiative->trackingCodes()->create([
                'tracking_code'     => $request->input('tracking_code'),
                'task_title'        => $request->input('task_title'),
                'fiscal_year'       => $request->input('fiscal_year'),
                'funding_type_id'   => $request->input('funding_type_id'),
                'specificity_level' => $specificity,
                'status'            => 'DRAFT',
                'order_pdf_path'    => $pdfPath,
            ]);

            if ($specificity === '2_CATEGORY') {
                foreach ($request->input('targets') as $target) {
                    $trackingCode->targets()->create([
                        'category_id'   => $target['category_id'],
                        'planned_qty'   => $target['planned_qty'],
                        'economic_code' => $target['economic_code'] ?? null,
                    ]);
                }
            } elseif ($specificity === '3_MATRIX') {
                $categories = array_unique($request->input('matrix_categories', [])); 
                $locations = array_unique($request->input('matrix_locations', []));   
                $values = $request->input('matrix_values', []);         

                foreach ($categories as $catId) {
                    $colSum = 0;
                    foreach ($locations as $rowIndex => $locId) {
                        $colSum += (int) ($values[$rowIndex][$catId] ?? 0);
                    }

                    $econCode = $request->input("matrix_economic_codes.{$catId}");
                    $target = $trackingCode->targets()->create([
                        'category_id'   => $catId,
                        'planned_qty'   => $colSum,
                        'economic_code' => $econCode ?? null,
                    ]);

                    foreach ($locations as $rowIndex => $locId) {
                        $qty = (int) ($values[$rowIndex][$catId] ?? 0);
                        if ($qty > 0) { 
                            $target->allocations()->create([
                                'location_id'   => $locId,
                                'allocated_qty' => $qty,
                            ]);
                        }
                    }
                }
            }

            // Save Geographical Scope
            $geoOverride = $request->input('geo_override');
            $trackingCode->scopes()->create([
                'dimension'   => 'GEOGRAPHY',
                'target_type' => $geoOverride,
                'target_id'   => $geoOverride === 'GeoArea' ? $request->input('geo_area_id') : null,
            ]);

            // Save Participants Scope
            $participantOverride = $request->input('participant_override');
            $trackingCode->scopes()->create([
                'dimension'   => 'PARTICIPANTS',
                'target_type' => $participantOverride,
                'target_id'   => null,
            ]);
        });

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Tracking Code created in DRAFT state successfully.');
    }

    public function update(Request $request, Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        if ($trackingCode->status !== 'DRAFT') {
            abort(403, 'Immutable Error: Active and Archived codes cannot be modified.');
        }

        $specificity = $trackingCode->specificity_level;

        $rules = [
            'task_title'      => 'required|string|max:255',
            'fiscal_year'     => 'required|string|max:20',
            'funding_type_id' => 'required|exists:gov_funding_types,id',
        ];

        if ($specificity === '2_CATEGORY' || $specificity === '3_MATRIX' || $specificity === '1_BLANKET') {
            $rules['geo_override']          = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id']           = 'required_if:geo_override,GeoArea';
            $rules['participant_override']  = 'required|in:Inherit,CrossTenant';
        }

        $request->validate($rules);

        DB::transaction(function () use ($request, $trackingCode, $specificity) {
            $trackingCode->update([
                'task_title'      => $request->input('task_title'),
                'fiscal_year'     => $request->input('fiscal_year'),
                'funding_type_id' => $request->input('funding_type_id'),
            ]);

            $trackingCode->targets()->delete();
            $trackingCode->scopes()->delete();

            if ($specificity === '2_CATEGORY') {
                foreach ($request->input('targets') as $target) {
                    $trackingCode->targets()->create([
                        'category_id'   => $target['category_id'],
                        'planned_qty'   => $target['planned_qty'],
                        'economic_code' => $target['economic_code'] ?? null,
                    ]);
                }
            } elseif ($specificity === '3_MATRIX') {
                $categories = array_unique($request->input('matrix_categories', [])); 
                $locations = array_unique($request->input('matrix_locations', []));   
                $values = $request->input('matrix_values', []);         

                foreach ($categories as $catId) {
                    $colSum = 0;
                    foreach ($locations as $rowIndex => $locId) {
                        $colSum += (int) ($values[$rowIndex][$catId] ?? 0);
                    }

                    $econCode = $request->input("matrix_economic_codes.{$catId}");
                    $target = $trackingCode->targets()->create([
                        'category_id'   => $catId,
                        'planned_qty'   => $colSum,
                        'economic_code' => $econCode ?? null,
                    ]);

                    foreach ($locations as $rowIndex => $locId) {
                        $qty = (int) ($values[$rowIndex][$catId] ?? 0);
                        if ($qty > 0) { 
                            $target->allocations()->create([
                                'location_id'   => $locId,
                                'allocated_qty' => $qty,
                            ]);
                        }
                    }
                }
            }

            // Sync Scopes
            $geoOverride = $request->input('geo_override');
            $trackingCode->scopes()->create([
                'dimension'   => 'GEOGRAPHY',
                'target_type' => $geoOverride,
                'target_id'   => $geoOverride === 'GeoArea' ? $request->input('geo_area_id') : null,
            ]);

            $participantOverride = $request->input('participant_override');
            $trackingCode->scopes()->create([
                'dimension'   => 'PARTICIPANTS',
                'target_type' => $participantOverride,
                'target_id'   => null,
            ]);
        });

        return redirect()->route('gov.tracking.initiatives.show', $trackingCode->initiative_id)
                         ->with('success', 'Tracking Code updated successfully.');
    }

    public function destroy(Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        if ($trackingCode->status !== 'DRAFT') {
            return redirect()->back()->with('error', 'Cannot delete active or archived tracking codes.');
        }

        $trackingCode->delete();
        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Tracking Code deleted.');
    }

    public function activate(Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authService->authorize($initiative, ['HEAD']);

        if ($trackingCode->status !== 'DRAFT') {
            abort(403, 'Invalid State Transition.');
        }

        $trackingCode->update(['status' => 'ACTIVE']);

        return redirect()->back()->with('success', "Tracking Code '{$trackingCode->tracking_code}' has been turned ACTIVE and is now locked.");
    }

    public function archive(Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authService->authorize($initiative, ['HEAD']);

        if ($trackingCode->status !== 'ACTIVE') {
            abort(403, 'Invalid State Transition.');
        }

        $trackingCode->update(['status' => 'ARCHIVED']);

        return redirect()->back()->with('success', "Tracking Code '{$trackingCode->tracking_code}' has been ARCHIVED.");
    }

    public function downloadPdf(TrackingCode $trackingCode)
    {
        if (!$trackingCode->order_pdf_path || !\Illuminate\Support\Facades\Storage::disk('local')->exists($trackingCode->order_pdf_path)) {
            abort(404, 'PDF Document not found.');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->download($trackingCode->order_pdf_path, $trackingCode->tracking_code . '_Order.pdf');
    }

    protected function getInitiativeAllowedLocations(Initiative $initiative)
    {
        return Location::withoutGlobalScopes()
            ->where('company_id', $initiative->owner_company_id)
            ->orderBy('name')
            ->get();
    }
}