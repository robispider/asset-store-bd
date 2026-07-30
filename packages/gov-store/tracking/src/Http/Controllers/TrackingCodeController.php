<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\FundingType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackingCodeController extends Controller
{
    public function create(Initiative $initiative)
    {
        $categories = Category::all();
        
        // Dynamic loading of allowed database sub-sources
        $fundingTypes = FundingType::where('primary_type', $initiative->primary_funding)->get();
        
        // Defensive Scope Fallback for Locations
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
        $request->validate([
            'tracking_code' => 'required|string|unique:gov_tracking_codes,tracking_code|max:100',
            'task_title'    => 'required|string|max:255',
            'order_pdf'     => $initiative->require_documents ? 'required|file|mimes:pdf|max:10240' : 'nullable|file|mimes:pdf|max:10240',
            
            // Fiscal Profile
            'fiscal_year'   => 'required|string|max:20',
            'funding_type_id' => 'required|exists:gov_funding_types,id',
            
            // Targets
            'targets'                 => 'required|array|min:1',
            'targets.*.category_id'   => 'required|exists:categories,id',
            'targets.*.planned_qty'   => 'required|integer|min:1',
            'targets.*.economic_code' => 'nullable|string|max:50',

            // Scopes
            'geo_override'            => 'required|in:Inherit,GeoArea',
            'geo_area_id'             => 'required_if:geo_override,GeoArea',
            'participant_override'    => 'required|in:Inherit,CrossTenant,SpecificLocations',
            'specific_location_ids'   => 'required_if:participant_override,SpecificLocations|array',
            'specific_location_ids.*' => 'exists:locations,id',
        ]);

        // Verify sub-source belongs to the Initiative's segment
        $selectedFund = FundingType::findOrFail($request->input('funding_type_id'));
        if ($selectedFund->primary_type !== $initiative->primary_funding) {
            throw ValidationException::withMessages(['funding_type_id' => 'Selected funding source does not belong to this Initiative\'s budget segment.']);
        }

        $categoryIds = array_column($request->input('targets'), 'category_id');
        if (count($categoryIds) !== count(array_unique($categoryIds))) {
            throw ValidationException::withMessages(['targets' => 'Duplicate categories are not allowed in the same Tracking Code.']);
        }

        DB::transaction(function () use ($request, $initiative) {
            $pdfPath = null;
            if ($request->hasFile('order_pdf')) {
                $pdfPath = $request->file('order_pdf')->store('tracking-orders/' . $initiative->id, 'local');
            }

            $trackingCode = $initiative->trackingCodes()->create([
                'tracking_code'   => $request->input('tracking_code'),
                'task_title'      => $request->input('task_title'),
                'fiscal_year'     => $request->input('fiscal_year'),
                'funding_type_id' => $request->input('funding_type_id'),
                'order_pdf_path'  => $pdfPath,
            ]);

            foreach ($request->input('targets') as $target) {
                $trackingCode->targets()->create([
                    'category_id'   => $target['category_id'],
                    'planned_qty'   => $target['planned_qty'],
                    'economic_code' => $target['economic_code'] ?? null,
                ]);
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
        });

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Tracking Code & Targets created successfully.');
    }
}