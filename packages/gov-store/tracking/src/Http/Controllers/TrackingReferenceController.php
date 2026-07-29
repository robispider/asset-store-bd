<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Models\TrackingType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackingReferenceController extends Controller
{
    public function index()
    {
        $references = TrackingReference::with('trackingType')->withCount('documents')->get();
        return view('govtracking::references.index', compact('references'));
    }

    public function create()
    {
        $types = TrackingType::all();
        return view('govtracking::references.create', compact('types'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tracking_type_id' => 'required|exists:gov_tracking_types,id',
            'reference_code' => 'required|string|unique:gov_tracking_references,reference_code|max:100',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:DRAFT,APPROVED,ACTIVE,SUSPENDED,COMPLETED,CANCELLED',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
        ]);

        TrackingReference::create($validated);

        return redirect()
            ->route('gov.tracking.references.index')
            ->with('success', trans('general.success'));
    }

    public function show(TrackingReference $reference)
    {
        $reference->load(['trackingType', 'documents.uploader']);
        return view('govtracking::references.show', compact('reference'));
    }

    public function edit(TrackingReference $reference)
    {
        $types = TrackingType::all();
        return view('govtracking::references.edit', compact('reference', 'types'));
    }

    public function update(Request $request, TrackingReference $reference)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:DRAFT,APPROVED,ACTIVE,SUSPENDED,COMPLETED,CANCELLED',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after_or_equal:effective_from',
        ]);

        $reference->update($validated);

        return redirect()
            ->route('gov.tracking.references.index')
            ->with('success', trans('general.success'));
    }
}
