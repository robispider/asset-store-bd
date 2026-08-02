<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\FundingType;
use GovStore\Tracking\Services\TrackingAuthorizationService; // Added
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        
        $locations = Location::all();
        $user = auth()->user();
        if ($locations->isEmpty() && $user) {
            $locations = $user->isSuperUser() 
                ? Location::withoutGlobalScopes()->get() 
                : Location::withoutGlobalScopes()->where('company_id', $user->company_id)->get();
        }

        $geoAreas = class_exists(GeoArea::class) ? GeoArea::all() : collect();

        return view('govtracking::tracking_codes.create', compact('initiative', 'categories', 'fundingTypes', 'locations', 'geoAreas'));
    }

    public function store(Request $request, Initiative $initiative)
    {
        // GATED (Centralized): HEAD or OFFICER can add tracking codes
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        $rules = [
            'tracking_code'     => 'required|string|unique:gov_tracking_codes,tracking_code|max:100',
            'task_title'        => 'required|string|max:255',
            'order_pdf'         => $initiative->require_documents ? 'required|file|mimes:pdf|max:10240' : 'nullable|file|mimes:pdf|max:10240',
            'fiscal_year'       => 'required|string|max:20',
            'funding_type_id'   => 'required|exists:gov_funding_types,id',
            'specificity_level' => 'required|in:1_BLANKET,2_CATEGORY,3_MATRIX',
        ];

        $specificity = $request->input('specificity_level');

        if ($specificity === '2_CATEGORY') {
            $rules['targets']                 = 'required|array|min:1';
            $rules['targets.*.category_id']   = 'required|exists:categories,id';
            $rules['targets.*.planned_qty']   = 'required|integer|min:1';
            $rules['targets.*.economic_code'] = 'nullable|string|max:50';
            
            $rules['geo_override']            = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id']             = 'required_if:geo_override,GeoArea';
            $rules['participant_override']    = 'required|in:Inherit,CrossTenant,SpecificLocations';
            $rules['specific_location_ids']   = 'required_if:participant_override,SpecificLocations|array';
            $rules['specific_location_ids.*'] = 'exists:locations,id';
        } elseif ($specificity === '3_MATRIX') {
            $rules['matrix_categories']       = 'required|array|min:1';
            $rules['matrix_categories.*']     = 'exists:categories,id';
            $rules['matrix_locations']        = 'required|array|min:1';
            $rules['matrix_locations.*']      = 'exists:locations,id';
            $rules['matrix_values']           = 'required|array';
            
            $rules['geo_override']            = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id']             = 'required_if:geo_override,GeoArea';
        } else {
            $rules['geo_override']            = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id']             = 'required_if:geo_override,GeoArea';
            $rules['participant_override']    = 'required|in:Inherit,CrossTenant,SpecificLocations';
            $rules['specific_location_ids']   = 'required_if:participant_override,SpecificLocations|array';
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
                $categories = $request->input('matrix_categories'); 
                $locations = $request->input('matrix_locations');   
                $values = $request->input('matrix_values');         

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

            if ($specificity === '3_MATRIX') {
                foreach ($request->input('matrix_locations') as $locId) {
                    $trackingCode->scopes()->create([
                        'dimension'   => 'PARTICIPANTS',
                        'target_type' => 'SpecificLocations',
                        'target_id'   => $locId,
                    ]);
                }
            } else {
                $participantOverride = $request->input('participant_override');
                if ($participantOverride === 'SpecificLocations' && $request->filled('specific_location_ids')) {
                    foreach ($request->input('specific_location_ids') as $locId) {
                        $trackingCode->scopes()->create([
                            'dimension'   => 'PARTICIPANTS',
                            'target_type' => 'SpecificLocations',
                            'target_id'   => $locId,
                        ]);
                    }
                } else {
                    $trackingCode->scopes()->create([
                        'dimension'   => 'PARTICIPANTS',
                        'target_type' => $participantOverride,
                        'target_id'   => null,
                    ]);
                }
            }
        });

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Tracking Code created in DRAFT state successfully.');
    }

    public function edit(Initiative $initiative, TrackingCode $trackingCode)
    {
        // GATED (Centralized): HEAD or OFFICER can edit targets
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        $categories = Category::all();
        $fundingTypes = FundingType::where('primary_type', $initiative->primary_funding)->get();
        
        $locations = Location::all();
        $user = auth()->user();
        if ($locations->isEmpty() && $user) {
            $locations = $user->isSuperUser() ? Location::withoutGlobalScopes()->get() : Location::withoutGlobalScopes()->where('company_id', $user->company_id)->get();
        }

        $geoAreas = class_exists(GeoArea::class) ? GeoArea::all() : collect();

        $activeGeo = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
        $activePart = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();
        $activeLocationIds = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->where('target_type', 'SpecificLocations')->pluck('target_id')->toArray();

        // Compile saved matrix cells
        $savedMatrixValues = [];
        $trackingCode->load('targets.allocations.location');
        foreach ($trackingCode->targets as $target) {
            foreach ($target->allocations as $alloc) {
                $savedMatrixValues[$alloc->location_id][$target->category_id] = $alloc->allocated_qty;
            }
        }

        return view('govtracking::tracking_codes.edit', compact(
            'initiative', 'trackingCode', 'categories', 'fundingTypes', 'locations', 'geoAreas', 'activeGeo', 'activePart', 'activeLocationIds', 'savedMatrixValues'
        ));
    }

    public function update(Request $request, Initiative $initiative, TrackingCode $trackingCode)
    {
        // GATED (Centralized): HEAD or OFFICER can update targets
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        $specificity = $trackingCode->specificity_level;

        $rules = [
            'task_title'      => 'required|string|max:255',
            'fiscal_year'     => 'required|string|max:20',
            'funding_type_id' => 'required|exists:gov_funding_types,id',
        ];

        if ($specificity === '2_CATEGORY') {
            $rules['targets']                 = 'required|array|min:1';
            $rules['targets.*.category_id']   = 'required|exists:categories,id';
            $rules['targets.*.planned_qty']   = 'required|integer|min:1';
            $rules['targets.*.economic_code'] = 'nullable|string|max:50';
            
            $rules['geo_override']            = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id']             = 'required_if:geo_override,GeoArea';
            $rules['participant_override']    = 'required|in:Inherit,CrossTenant,SpecificLocations';
            $rules['specific_location_ids']   = 'required_if:participant_override,SpecificLocations|array';
        } elseif ($specificity === '3_MATRIX') {
            $rules['matrix_categories']       = 'required|array|min:1';
            $rules['matrix_categories.*']     = 'exists:categories,id';
            $rules['matrix_locations']        = 'required|array|min:1';
            $rules['matrix_locations.*']      = 'exists:locations,id';
            $rules['matrix_values']           = 'required|array';
            
            $rules['geo_override']            = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id']             = 'required_if:geo_override,GeoArea';
        } else {
            $rules['geo_override']            = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id']             = 'required_if:geo_override,GeoArea';
            $rules['participant_override']    = 'required|in:Inherit,CrossTenant,SpecificLocations';
            $rules['specific_location_ids']   = 'required_if:participant_override,SpecificLocations|array';
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
                $categories = $request->input('matrix_categories'); 
                $locations = $request->input('matrix_locations');   
                $values = $request->input('matrix_values');         

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

            if ($specificity === '3_MATRIX') {
                foreach ($request->input('matrix_locations') as $locId) {
                    $trackingCode->scopes()->create([
                        'dimension'   => 'PARTICIPANTS',
                        'target_type' => 'SpecificLocations',
                        'target_id'   => $locId,
                    ]);
                }
            } else {
                $participantOverride = $request->input('participant_override');
                if ($participantOverride === 'SpecificLocations') {
                    foreach ($request->input('specific_location_ids') as $locId) {
                        $trackingCode->scopes()->create([
                            'dimension'   => 'PARTICIPANTS',
                            'target_type' => 'SpecificLocations',
                            'target_id'   => $locId,
                        ]);
                    }
                } else {
                    $trackingCode->scopes()->create([
                        'dimension'   => 'PARTICIPANTS',
                        'target_type' => $participantOverride,
                        'target_id'   => null,
                    ]);
                }
            }
        });

        return redirect()->route('gov.tracking.initiatives.show', $trackingCode->initiative_id)
                         ->with('success', 'Tracking Code updated successfully.');
    }

    public function destroy(Initiative $initiative, TrackingCode $trackingCode)
    {
        // GATED (Centralized): HEAD or OFFICER can delete targets
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
        // GATED (Centralized): Only HEAD (Operation Head) can activate
        $this->authService->authorize($initiative, ['HEAD']);

        if ($trackingCode->status !== 'DRAFT') {
            abort(403, 'Invalid State Transition.');
        }

        $trackingCode->update(['status' => 'ACTIVE']);

        return redirect()->back()->with('success', "Tracking Code '{$trackingCode->tracking_code}' has been turned ACTIVE and is now locked.");
    }

    public function archive(Initiative $initiative, TrackingCode $trackingCode)
    {
        // GATED (Centralized): Only HEAD (Operation Head) can archive
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
}