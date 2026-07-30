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
        $request->validate([
            'tracking_code'   => 'required|string|unique:gov_tracking_codes,tracking_code|max:100',
            'task_title'      => 'required|string|max:255',
            'order_pdf'       => $initiative->require_documents ? 'required|file|mimes:pdf|max:10240' : 'nullable|file|mimes:pdf|max:10240',
            'fiscal_year'     => 'required|string|max:20',
            'funding_type_id' => 'required|exists:gov_funding_types,id',
            'targets'                 => 'required|array|min:1',
            'targets.*.category_id'   => 'required|exists:categories,id',
            'targets.*.planned_qty'   => 'required|integer|min:1',
            'targets.*.economic_code' => 'nullable|string|max:50',
            'geo_override'            => 'required|in:Inherit,GeoArea',
            'geo_area_id'             => 'required_if:geo_override,GeoArea',
            'participant_override'    => 'required|in:Inherit,CrossTenant,SpecificLocations',
            'specific_location_ids'   => 'required_if:participant_override,SpecificLocations|array',
            'specific_location_ids.*' => 'exists:locations,id',
        ]);

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
                'status'          => 'DRAFT', // Born as Draft
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
                         ->with('success', 'Tracking Code created in DRAFT state successfully.');
    }

    public function edit(Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authorizeManagement($initiative);

        // ENFORCE IMMUTABILITY
        if ($trackingCode->status !== 'DRAFT') {
            return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                             ->with('error', 'Immutable Error: This Tracking Code has been activated or archived and can no longer be edited.');
        }

        $categories = Category::all();
        $fundingTypes = FundingType::where('primary_type', $initiative->primary_funding)->get();
        
        $locations = Location::all();
        $user = auth()->user();
        if ($locations->isEmpty() && $user) {
            $locations = $user->isSuperUser() ? Location::withoutGlobalScopes()->get() : Location::withoutGlobalScopes()->where('company_id', $user->company_id)->get();
        }

        $geoAreas = class_exists(GeoArea::class) ? GeoArea::all() : collect();

        // Resolve active values for pre-population
        $activeGeo = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
        $activePart = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();
        $activeLocationIds = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->where('target_type', 'SpecificLocations')->pluck('target_id')->toArray();

        return view('govtracking::tracking_codes.edit', compact(
            'initiative', 'trackingCode', 'categories', 'fundingTypes', 'locations', 'geoAreas', 'activeGeo', 'activePart', 'activeLocationIds'
        ));
    }

    public function update(Request $request, Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authorizeManagement($initiative);

        if ($trackingCode->status !== 'DRAFT') {
            abort(403, 'Immutable Error: Active and Archived codes cannot be modified.');
        }

        $request->validate([
            'task_title'    => 'required|string|max:255',
            'fiscal_year'   => 'required|string|max:20',
            'funding_type_id' => 'required|exists:gov_funding_types,id',
            'targets'                 => 'required|array|min:1',
            'targets.*.category_id'   => 'required|exists:categories,id',
            'targets.*.planned_qty'   => 'required|integer|min:1',
            'targets.*.economic_code' => 'nullable|string|max:50',
            'geo_override'            => 'required|in:Inherit,GeoArea',
            'geo_area_id'             => 'required_if:geo_override,GeoArea',
            'participant_override'    => 'required|in:Inherit,CrossTenant,SpecificLocations',
            'specific_location_ids'   => 'required_if:participant_override,SpecificLocations|array',
        ]);

        DB::transaction(function () use ($request, $trackingCode) {
            // Update core attributes
            $trackingCode->update([
                'task_title'      => $request->input('task_title'),
                'fiscal_year'     => $request->input('fiscal_year'),
                'funding_type_id' => $request->input('funding_type_id'),
            ]);

            // Sync targets (delete old, save new)
            $trackingCode->targets()->delete();
            foreach ($request->input('targets') as $target) {
                $trackingCode->targets()->create([
                    'category_id'   => $target['category_id'],
                    'planned_qty'   => $target['planned_qty'],
                    'economic_code' => $target['economic_code'] ?? null,
                ]);
            }

            // Sync scopes (delete old, save new)
            $trackingCode->scopes()->delete();
            
            $geoOverride = $request->input('geo_override');
            $trackingCode->scopes()->create([
                'dimension'   => 'GEOGRAPHY',
                'target_type' => $geoOverride,
                'target_id'   => $geoOverride === 'GeoArea' ? $request->input('geo_area_id') : null,
            ]);

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
        });

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Tracking Code updated successfully.');
    }

    public function destroy(Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authorizeManagement($initiative);

        if ($trackingCode->status !== 'DRAFT') {
            return redirect()->back()->with('error', 'Cannot delete active or archived tracking codes.');
        }

        $trackingCode->delete();
        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
                         ->with('success', 'Tracking Code deleted.');
    }

    /**
     * Promote a Tracking Code from DRAFT to ACTIVE.
     * This locks the targets and allows storekeepers to select it in GRNs.
     */
    public function activate(Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authorizeManagement($initiative);

        if ($trackingCode->status !== 'DRAFT') {
            abort(403, 'Invalid State Transition.');
        }

        $trackingCode->update(['status' => 'ACTIVE']);

        return redirect()->back()->with('success', "Tracking Code '{$trackingCode->tracking_code}' has been turned ACTIVE and is now locked.");
    }

    /**
     * Retire a Tracking Code from ACTIVE to ARCHIVED.
     * Disables selection in future GRNs while keeping historical ledger links intact.
     */
    public function archive(Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->authorizeManagement($initiative);

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

    /**
     * Protect routes with the formalized GovStore Management Team permissions.
     */
    protected function authorizeManagement(Initiative $initiative)
    {
        $user = auth()->user();
        if (!$user) abort(403);

        if ($user->isSuperUser()) return; // Global Superuser Bypass

        // Check if user is the explicit Company Admin of the Owning Ministry OR
        // if they are mapped to local PMO management roles in the same location
        $isCompanyAdmin = $user->company_id === $initiative->owner_company_id;
        
        $isLocalPMOAdmin = DB::table('gov_office_responsibilities')
            ->where('user_id', $user->id)
            ->where('location_id', $initiative->manager_location_id)
            ->whereIn('role_slug', ['office_admin', 'storekeeper'])
            ->exists();

        if (!$isCompanyAdmin && !$isLocalPMOAdmin) {
            abort(403, 'Unauthorized. Only members of the Initiative Management Team or Ministry Admins can execute configurations.');
        }
    }
}