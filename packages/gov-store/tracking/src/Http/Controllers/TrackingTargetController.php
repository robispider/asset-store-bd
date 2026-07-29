<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\AssetModel;
use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Models\TrackingTarget;
use Illuminate\Http\Request;

class TrackingTargetController extends Controller
{
    public function index(TrackingReference $reference)
    {
        $reference->load('targets.category', 'targets.assetModel');
        $categories = Category::all();
        $models = AssetModel::all();

        return view('govtracking::references.targets', compact('reference', 'categories', 'models'));
    }

    public function store(Request $request, TrackingReference $reference)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'model_id' => 'nullable|exists:models,id',
            'planned_qty' => 'required|integer|min:1',
        ]);

        // Prevent duplicate records via validation layer
        $exists = TrackingTarget::where('tracking_reference_id', $reference->id)
            ->where('category_id', $validated['category_id'])
            ->where('model_id', $validated['model_id'])
            ->exists();

        if ($exists) {
            return redirect()
                ->route('gov.tracking.references.targets.index', $reference->id)
                ->with('error', 'A planning target for this category and model combination already exists.');
        }

        $reference->targets()->create($validated);

        return redirect()
            ->route('gov.tracking.references.targets.index', $reference->id)
            ->with('success', 'Planning target created successfully.');
    }

    public function destroy(TrackingReference $reference, TrackingTarget $target)
    {
        $target->delete();

        return redirect()
            ->route('gov.tracking.references.targets.index', $reference->id)
            ->with('success', 'Planning target removed successfully.');
    }
}
