<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use GovStore\Tracking\Models\TrackingType;
use Illuminate\Http\Request;

class TrackingTypeController extends Controller
{
    public function index()
    {
        $types = TrackingType::withCount('references')->get();
        return view('govtracking::types.index', compact('types'));
    }

    public function create()
    {
        return view('govtracking::types.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:gov_tracking_types,code|max:50',
            'display_name' => 'required|string|max:255',
            'icon' => 'required|string|max:100',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'validation_policy' => 'required|in:INFORM_ONLY,WARN,REQUIRE_OVERRIDE,BLOCK',
        ]);

        TrackingType::create($validated);

        return redirect()
            ->route('gov.tracking.types.index')
            ->with('success', trans('general.success'));
    }

    public function edit(TrackingType $type)
    {
        return view('govtracking::types.edit', compact('type'));
    }

    public function update(Request $request, TrackingType $type)
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'icon' => 'required|string|max:100',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'validation_policy' => 'required|in:INFORM_ONLY,WARN,REQUIRE_OVERRIDE,BLOCK',
        ]);

        $type->update($validated);

        return redirect()
            ->route('gov.tracking.types.index')
            ->with('success', trans('general.success'));
    }

    public function destroy(TrackingType $type)
    {
        if ($type->references()->exists()) {
            return redirect()
                ->route('gov.tracking.types.index')
                ->with('error', 'Cannot delete a tracking type that has active references.');
        }

        $type->delete();

        return redirect()
            ->route('gov.tracking.types.index')
            ->with('success', trans('general.success'));
    }
}
