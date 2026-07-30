<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use GovStore\Tracking\Models\FundingType;
use Illuminate\Http\Request;

class FundingTypeController extends Controller
{
    public function index()
    {
        if (!auth()->user()->isSuperUser()) {
            abort(403, 'Unauthorized. Only Super Administrators can access system configurations.');
        }

        $fundingTypes = FundingType::withCount('trackingCodes')->get();
        return view('govtracking::funding_types.index', compact('fundingTypes'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->isSuperUser()) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'primary_type' => 'required|in:ADP,REVENUE,OTHER',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        FundingType::create($validated);
        return redirect()->back()->with('success', 'Funding Source added successfully.');
    }

    public function destroy(FundingType $fundingType)
    {
        if (!auth()->user()->isSuperUser()) {
            abort(403, 'Unauthorized.');
        }

        if ($fundingType->trackingCodes()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete a funding source that is currently mapped to active tasks.');
        }
        
        $fundingType->delete();
        return redirect()->back()->with('success', 'Funding Source removed.');
    }
}