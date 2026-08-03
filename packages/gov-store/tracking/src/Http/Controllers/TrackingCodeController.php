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
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TrackingCodeController extends Controller
{
    protected TrackingAuthorizationService $authService;

    public function __construct(TrackingAuthorizationService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Secure Read-Only Task Component Viewer
     * Enforces identical lifecycle and scope validations as the GRN verify-code handshake.
     */
    public function viewTaskComponent(TrackingCode $trackingCode)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        // 1. Resolve user's operating location ID
        $locationId = $user->location_id;
        if (!$locationId) {
            abort(403, 'Operational Block: Your user account is not currently assigned to any operating office or location.');
        }

        // 2. Eager load parent initiative without global scopes
        $trackingCode->load([
            'initiative' => function($query) {
                $query->withoutGlobalScopes();
            }
        ]);

        $initiative = $trackingCode->initiative;
        if (!$initiative) {
            abort(403, 'Unauthorized. Parent initiative is missing or inaccessible.');
        }

        // 3. Verify task lifecycle state
        if ($trackingCode->status !== 'ACTIVE') {
            abort(403, 'Inactive Task: This tracking code component is not currently in an ACTIVE operational state.');
        }

        // 4. Verify parent initiative lifecycle state
        if ($initiative->status !== 'Active') {
            $statusMsg = '';
            if ($initiative->status === 'Planning') {
                $statusMsg = "The initiative '{$initiative->title}' is currently in the Setup (Planning) phase and is not yet open for procurement operations.";
            } elseif ($initiative->status === 'Closed') {
                $statusMsg = "The initiative '{$initiative->title}' has been officially Closed. New physical receipts (GRNs) under this budget are suspended.";
            } else {
                $statusMsg = "The initiative '{$initiative->title}' has been Archived. All historical records are locked against future ledger transactions.";
            }
            abort(403, "Operational Block: {$statusMsg}");
        }

        // 5. Verify Geographical and Organizational Visibility Scopes
        $scopeValidator = app(\GovStore\Tracking\Services\ScopeValidatorService::class);
        $scopeCheck = $scopeValidator->validateExecutionScope($trackingCode, $locationId);
        if (!$scopeCheck['is_valid']) {
            abort(403, $scopeCheck['message']);
        }

        // 6. Eager load targets and dynamic classifications
        $trackingCode->load([
            'targets.category' => function($query) {
                $query->withTrashed();
            },
            'fundingType',
            'scopes'
        ]);

        $projectionRepo = app(\GovStore\Tracking\Repositories\TrackingProjectionRepositoryInterface::class);

        // 7. Compile target metrics based on the task's delivery strategy
        if ($trackingCode->specificity_level === '3_MATRIX') {
            // Compile precise targets configured for the storekeeper's specific office
            foreach ($trackingCode->targets as $target) {
                $allocation = DB::table('gov_tracking_allocations')
                    ->where('target_id', $target->id)
                    ->where('location_id', $locationId)
                    ->first();

                if ($allocation) {
                    $target->allocated_qty = (int) $allocation->allocated_qty;
                    $target->received_qty = (int) DB::table('gov_tracking_associations')
                        ->where('tracking_code_id', $trackingCode->id)
                        ->where('category_id', $target->category_id)
                        ->where('location_id', $locationId)
                        ->where('status', 'ACTIVE')
                        ->sum('quantity');
                    $target->remaining_qty = max(0, $target->allocated_qty - $target->received_qty);
                } else {
                    $target->allocated_qty = 0;
                    $target->received_qty = 0;
                    $target->remaining_qty = 0;
                }
            }
        } else {
            // Compile totals for level 2 category targets
            foreach ($trackingCode->targets as $target) {
                $progress = $projectionRepo->getTargetProgress($trackingCode->id, $target->category_id);
                $target->received_qty = (int) ($progress['received'] ?? 0);
                $target->remaining_qty = max(0, $target->planned_qty - $target->received_qty);
            }
        }

        $locationName = Location::withoutGlobalScopes()->where('id', $locationId)->value('name') ?? 'Your Assigned Office';

        return view('govtracking::tracking_codes.view_task_component', compact(
            'initiative', 'trackingCode', 'locationId', 'locationName'
        ));
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

        $trackingCode->load([
            'targets.allocations.location' => function($query) {
                $query->withoutGlobalScopes();
            }
        ]);

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

            $query = Location::withoutGlobalScopes();

            if ($request->input('participant_override') === 'Inherit') {
                $query->where('company_id', $initiative->owner_company_id);
            }

            if ($request->input('geo_override') === 'GeoArea' && $request->filled('geo_area_id')) {
                $geoArea = GeoArea::find($request->input('geo_area_id'));
                
                if ($geoArea) {
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
            Log::error('GovStore: searchOffices API Failure: ' . $e->getMessage(), [
                'exception' => $e,
                'request'   => $request->all()
            ]);

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