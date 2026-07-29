<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Location;
use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Models\TrackingScope;
use Illuminate\Http\Request;

class TrackingScopeController extends Controller
{
    public function index(TrackingReference $reference)
    {
        $reference->load('scopes');
        $companies = Company::all();
        $locations = Location::all();
        
        $geoAreas = [];
        if (class_exists('GovStore\GeoAreas\Models\GeoArea')) {
            $geoAreas = \GovStore\GeoAreas\Models\GeoArea::all();
        }

        return view('govtracking::references.scopes', compact('reference', 'companies', 'locations', 'geoAreas'));
    }

    public function store(Request $request, TrackingReference $reference)
    {
        $validated = $request->validate([
            'dimension' => 'required|in:OWNERSHIP,VISIBILITY,APPLICABILITY,ADMINISTRATION',
            'target_type' => 'required|in:Company,Location,GeoArea,Global',
            'target_id' => 'nullable|integer',
        ]);

        if ($validated['target_type'] !== 'Global' && empty($validated['target_id'])) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['target_id' => 'Target entity is required unless configuring a Global scope.']);
        }

        if ($validated['target_type'] === 'Global') {
            $validated['target_id'] = null;
        }

        // Prevent exact duplicate records
        $exists = TrackingScope::where('tracking_reference_id', $reference->id)
            ->where('dimension', $validated['dimension'])
            ->where('target_type', $validated['target_type'])
            ->where('target_id', $validated['target_id'])
            ->exists();

        if ($exists) {
            return redirect()
                ->route('gov.tracking.references.scopes.index', $reference->id)
                ->with('error', 'This specific scope configuration already exists.');
        }

        $reference->scopes()->create($validated);

        return redirect()
            ->route('gov.tracking.references.scopes.index', $reference->id)
            ->with('success', 'Scope rule added successfully.');
    }

    public function destroy(TrackingReference $reference, TrackingScope $scope)
    {
        $scope->delete();

        return redirect()
            ->route('gov.tracking.references.scopes.index', $reference->id)
            ->with('success', 'Scope rule removed successfully.');
    }
}
