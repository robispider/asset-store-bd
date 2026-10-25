<?php

namespace GovStore\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Location;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\Tracking\Models\FundingType;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingScope;
use GovStore\Tracking\Models\TrackingTimeline;
use GovStore\Tracking\Services\ProgrammeVerifier;
use GovStore\Tracking\Services\ScopeValidatorService;
use GovStore\Tracking\Services\TrackingAuthorizationService;
use GovStore\Tracking\Services\TrackingMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
    /**
     * Secure Read-Only Task Component Viewer
     * Enforces geographical and organizational scope checks, resolves operational
     * head contacts, and compiles target allocations for storekeeper verification.
     */
    public function viewTaskComponent(TrackingCode $trackingCode)
    {
        $context = app(TenantContext::class);
        $locationId = (int) $context->locationId;
        $managed = Initiative::find($trackingCode->initiative_id);
        if (! $managed || ! $this->authService->allows($managed)) {
            $trackingCode = app(ProgrammeVerifier::class)->resolve($trackingCode->tracking_code, $locationId);
        } else {
            $trackingCode->setRelation('initiative', $managed);
        }
        $trackingCode->initiative->load(['ownerCompany', 'operationUnits.user']);
        $initiative = $trackingCode->initiative;

        // 6. Eager load targets and structural scope parameters
        $trackingCode->load([
            'targets.category' => function ($query) {
                $query->withoutGlobalScopes();
            },
            'fundingType',
            'scopes',
        ]);

        // 7. Extract specific administrative support contact cards
        $operationHead = $initiative->operationUnits->where('designation', 'HEAD')->first();
        $operationOfficer = $initiative->operationUnits->where('designation', 'OFFICER')->first();

        // 8. Compile target allocations for display
        if ($trackingCode->specificity_level === '3_MATRIX') {
            foreach ($trackingCode->targets as $target) {
                $allocation = DB::table('gov_tracking_allocations')
                    ->where('target_id', $target->id)
                    ->where('location_id', $locationId)
                    ->first();

                if ($allocation) {
                    $target->allocated_qty = (int) $allocation->allocated_qty;
                } else {
                    $target->allocated_qty = 0;
                }
            }
        }

        $locationName = Location::withoutGlobalScopes()->where('id', $locationId)->value('name') ?? __('govtracking::general.ui.assigned_office');

        return view('govtracking::tracking_codes.view_task_component', compact(
            'initiative', 'trackingCode', 'locationId', 'locationName', 'operationHead', 'operationOfficer'
        ));
    }

    public function show(Initiative $initiative, TrackingCode $trackingCode)
    {
        abort_unless((int) $trackingCode->initiative_id === (int) $initiative->id, 404);
        $monitor = app(TrackingMonitorService::class)->programme($initiative);
        $code = collect($monitor['codes'])->firstWhere('id', $trackingCode->id);
        abort_unless($code, 404);
        $codeOffices = [];
        $service = app(TrackingMonitorService::class);
        foreach ($service->codeOfficeIds($code) as $id) {
            if (! isset($monitor['location_meta'][$id])) {
                continue;
            }
            $slice = $service->slice($code, $id);
            $codeOffices[] = $monitor['location_meta'][$id] + $slice + ['share' => $slice['allocated'] > 0 ? $slice['done'] / $slice['allocated'] : null];
        }

        return view('govtracking::tracking_codes.show', compact('initiative', 'trackingCode', 'monitor', 'code', 'codeOffices'));
    }

    public function create(Initiative $initiative)
    {
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);
        $categories = Category::all();
        $fundingTypes = FundingType::where('primary_type', $initiative->primary_funding)->get();
        $geoAreas = $this->selectedGeoAreas(old('geo_area_id'));

        return view('govtracking::tracking_codes.create', compact('initiative', 'categories', 'fundingTypes', 'geoAreas'));
    }

    public function edit(Initiative $initiative, TrackingCode $trackingCode)
    {
        $this->assertTask($initiative, $trackingCode);
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);
        abort_unless($trackingCode->status === 'DRAFT', 409);

        $categories = Category::all();
        $fundingTypes = FundingType::where('primary_type', $initiative->primary_funding)->get();

        $activeGeo = $trackingCode->scopes->where('dimension', 'GEOGRAPHY')->first();
        $geoAreas = $this->selectedGeoAreas(old('geo_area_id', $activeGeo?->target_id));
        $activePart = $trackingCode->scopes->where('dimension', 'PARTICIPANTS')->first();

        $trackingCode->load([
            'targets.allocations.location' => function ($query) {
                $query->withoutGlobalScopes();
            },
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
        $request->validate([
            'initiative_id' => 'required|exists:gov_initiatives,id',
            'geo_override' => 'required|in:Inherit,GeoArea',
            'geo_area_id' => 'nullable|required_if:geo_override,GeoArea|integer|exists:gov_geo_areas,GeoAreaId',
            'participant_override' => 'required|in:Inherit,CrossTenant',
            'q' => 'nullable|string',
        ]);

        $term = $request->input('q');
        $initiative = Initiative::findOrFail($request->input('initiative_id'));
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        $query = Location::withoutGlobalScopes();

        if ($request->input('participant_override') === 'Inherit') {
            $query->where('company_id', $initiative->owner_company_id);
        }

        if ($request->input('geo_override') === 'GeoArea' && $request->filled('geo_area_id')) {
            $geoArea = GeoArea::find($request->input('geo_area_id'));

            if ($geoArea) {
                $profileTable = (new LocationProfile)->getTable();
                $geoTable = (new GeoArea)->getTable();

                $query->whereIn('id', function ($subQuery) use ($geoArea, $profileTable, $geoTable) {
                    $subQuery->select('location_id')
                        ->from($profileTable)
                        ->join($geoTable, $profileTable.'.geo_area_id', '=', $geoTable.'.GeoAreaId')
                        ->where($geoTable.'.hid', 'LIKE', $geoArea->hid.'%');
                });
            }
        }

        if (! empty($term)) {
            $query->where('name', 'LIKE', "%{$term}%");
        }

        $locations = $query->limit(20)->get();

        $results = $locations->map(function ($loc) {
            return [
                'id' => $loc->id,
                'text' => $loc->name,
            ];
        });

        return response()->json(['results' => $results]);

    }

    public function store(Request $request, Initiative $initiative)
    {
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        $initiative = Initiative::whereKey($initiative->id)->lockForUpdate()->firstOrFail();
        abort_unless(in_array($initiative->status, ['Planning', 'Active'], true), 409);

        $rules = [
            'tracking_code' => 'required|string|unique:gov_tracking_codes,tracking_code|max:100',
            'task_title' => 'required|string|max:255',
            'order_pdf' => $initiative->require_documents ? 'required|file|mimes:pdf|max:10240' : 'nullable|file|mimes:pdf|max:10240',
            'fiscal_year' => 'required|string|max:20',
            'funding_type_id' => 'required|exists:gov_funding_types,id',
            'specificity_level' => 'required|in:1_BLANKET,2_CATEGORY,3_MATRIX',
        ];

        $specificity = $request->input('specificity_level');

        if ($specificity === '2_CATEGORY' || $specificity === '3_MATRIX' || $specificity === '1_BLANKET') {
            $rules['geo_override'] = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id'] = 'required_if:geo_override,GeoArea';
            $rules['participant_override'] = 'required|in:Inherit,CrossTenant';
        }

        $this->validateTargets($request, $initiative, $specificity, $rules);

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
                $pdfPath = $request->file('order_pdf')->store('tracking-orders/'.$initiative->id, 'local');
            }

            $trackingCode = $initiative->trackingCodes()->create([
                'tracking_code' => $request->input('tracking_code'),
                'task_title' => $request->input('task_title'),
                'fiscal_year' => $request->input('fiscal_year'),
                'funding_type_id' => $request->input('funding_type_id'),
                'specificity_level' => $specificity,
                'status' => 'DRAFT',
                'order_pdf_path' => $pdfPath,
            ]);

            if ($specificity === '2_CATEGORY') {
                foreach ($request->input('targets') as $target) {
                    $trackingCode->targets()->create([
                        'category_id' => $target['category_id'],
                        'planned_qty' => $target['planned_qty'],
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
                        'category_id' => $catId,
                        'planned_qty' => $colSum,
                        'economic_code' => $econCode ?? null,
                    ]);

                    foreach ($locations as $rowIndex => $locId) {
                        $qty = (int) ($values[$rowIndex][$catId] ?? 0);
                        if ($qty > 0) {
                            $target->allocations()->create([
                                'location_id' => $locId,
                                'allocated_qty' => $qty,
                            ]);
                        }
                    }
                }
            }

            $geoOverride = $request->input('geo_override');
            $trackingCode->scopes()->create([
                'dimension' => 'GEOGRAPHY',
                'target_type' => $geoOverride,
                'target_id' => $geoOverride === 'GeoArea' ? $request->input('geo_area_id') : null,
            ]);

            $participantOverride = $request->input('participant_override');
            $trackingCode->scopes()->create([
                'dimension' => 'PARTICIPANTS',
                'target_type' => $participantOverride,
                'target_id' => null,
            ]);
            $this->log($initiative, $trackingCode, 'CODE_CREATED');
        });

        return redirect()->route('gov.tracking.initiatives.show', $initiative->id)
            ->with('success', 'Tracking Code created in DRAFT state successfully.');
    }

    public function update(Request $request, Initiative $initiative, TrackingCode $trackingCode)
    {
        $trackingCode = $this->assertTask($initiative, $trackingCode, true);
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        if ($trackingCode->status !== 'DRAFT') {
            abort(403, 'Immutable Error: Active and Archived codes cannot be modified.');
        }

        $specificity = $trackingCode->specificity_level;

        $rules = [
            'task_title' => 'required|string|max:255',
            'fiscal_year' => 'required|string|max:20',
            'funding_type_id' => 'required|exists:gov_funding_types,id',
        ];

        if ($specificity === '2_CATEGORY' || $specificity === '3_MATRIX' || $specificity === '1_BLANKET') {
            $rules['geo_override'] = 'required|in:Inherit,GeoArea';
            $rules['geo_area_id'] = 'required_if:geo_override,GeoArea';
            $rules['participant_override'] = 'required|in:Inherit,CrossTenant';
        }

        $this->validateTargets($request, $initiative, $specificity, $rules);

        DB::transaction(function () use ($request, $trackingCode, $specificity) {
            $trackingCode->update([
                'task_title' => $request->input('task_title'),
                'fiscal_year' => $request->input('fiscal_year'),
                'funding_type_id' => $request->input('funding_type_id'),
            ]);

            $trackingCode->targets()->delete();
            $trackingCode->scopes()->delete();

            if ($specificity === '2_CATEGORY') {
                foreach ($request->input('targets') as $target) {
                    $trackingCode->targets()->create([
                        'category_id' => $target['category_id'],
                        'planned_qty' => $target['planned_qty'],
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
                        'category_id' => $catId,
                        'planned_qty' => $colSum,
                        'economic_code' => $econCode ?? null,
                    ]);

                    foreach ($locations as $rowIndex => $locId) {
                        $qty = (int) ($values[$rowIndex][$catId] ?? 0);
                        if ($qty > 0) {
                            $target->allocations()->create([
                                'location_id' => $locId,
                                'allocated_qty' => $qty,
                            ]);
                        }
                    }
                }
            }

            $geoOverride = $request->input('geo_override');
            $trackingCode->scopes()->create([
                'dimension' => 'GEOGRAPHY',
                'target_type' => $geoOverride,
                'target_id' => $geoOverride === 'GeoArea' ? $request->input('geo_area_id') : null,
            ]);

            $participantOverride = $request->input('participant_override');
            $trackingCode->scopes()->create([
                'dimension' => 'PARTICIPANTS',
                'target_type' => $participantOverride,
                'target_id' => null,
            ]);
        });

        return redirect()->route('gov.tracking.initiatives.show', $trackingCode->initiative_id)
            ->with('success', 'Tracking Code updated successfully.');
    }

    public function destroy(Initiative $initiative, TrackingCode $trackingCode)
    {
        $trackingCode = $this->assertTask($initiative, $trackingCode, true);
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);

        if ($trackingCode->status !== 'DRAFT') {
            return redirect()->back()->with('error', 'Cannot delete active or archived tracking codes.');
        }

        $paths = $trackingCode->documents()->pluck('file_path')->all();
        $orderPath = $trackingCode->order_pdf_path;
        $this->log($initiative, $trackingCode, 'CODE_DELETED');
        $trackingCode->delete();
        DB::afterCommit(function () use ($paths, $orderPath) {
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }
            if ($orderPath) {
                Storage::disk('local')->delete($orderPath);
            }
        });

        // The Manage tab deletes drafts in place; elsewhere the programme monitor is the landing page.
        $manage = route('gov.tracking.initiatives.manage', $initiative->id);

        return redirect()->to(str_starts_with(url()->previous(), $manage) ? url()->previous() : route('gov.tracking.initiatives.show', $initiative->id))
            ->with('success', 'Tracking Code deleted.');
    }

    public function activate(Initiative $initiative, TrackingCode $trackingCode)
    {
        $trackingCode = $this->assertTask($initiative, $trackingCode, true);
        $this->authService->authorize($initiative, ['HEAD']);

        if ($trackingCode->status !== 'DRAFT') {
            abort(403, 'Invalid State Transition.');
        }

        $trackingCode->update(['status' => 'ACTIVE', 'activated_at' => now(), 'activation_is_approximate' => false]);
        $this->log($initiative, $trackingCode, 'CODE_ACTIVATED');

        return redirect()->back()->with('success', "Tracking Code '{$trackingCode->tracking_code}' has been turned ACTIVE and is now locked.");
    }

    public function archive(Initiative $initiative, TrackingCode $trackingCode)
    {
        $trackingCode = $this->assertTask($initiative, $trackingCode, true);
        $this->authService->authorize($initiative, ['HEAD']);

        if ($trackingCode->status !== 'ACTIVE') {
            abort(403, 'Invalid State Transition.');
        }

        $trackingCode->update(['status' => 'ARCHIVED']);
        $this->log($initiative, $trackingCode, 'CODE_ARCHIVED');

        return redirect()->back()->with('success', "Tracking Code '{$trackingCode->tracking_code}' has been ARCHIVED.");
    }

    /**
     * Attach the order PDF a programme requires to a code that has none yet. Drafts may also replace it;
     * an active code's order is evidence for receipts already counted, so it is only ever added, never swapped.
     */
    public function attachOrder(Request $request, Initiative $initiative, TrackingCode $trackingCode)
    {
        abort_unless((int) $trackingCode->initiative_id === (int) $initiative->id, 404);
        $this->authService->authorize($initiative, ['HEAD', 'OFFICER']);
        $request->validate(['order_pdf' => 'required|file|mimes:pdf|max:10240']);
        $path = $request->file('order_pdf')->store('tracking-orders/'.$initiative->id, 'local');
        try {
            $previous = DB::transaction(function () use ($initiative, $trackingCode, $request, $path) {
                $task = $this->assertTask($initiative, $trackingCode, true);
                abort_unless($task->status === 'DRAFT' || ($task->status === 'ACTIVE' && ! $task->order_pdf_path), 409);
                $previous = $task->order_pdf_path;
                $task->update(['order_pdf_path' => $path]);
                $this->log($initiative, $task, 'ORDER_ATTACHED', basename($request->file('order_pdf')->getClientOriginalName()));

                return $previous;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        if ($previous) {
            Storage::disk('local')->delete($previous);
        }

        return redirect()->back()->with('success', __('govtracking::general.manage.order_attached', ['code' => $trackingCode->tracking_code]));
    }

    private function log(Initiative $initiative, TrackingCode $code, string $event, ?string $description = null): void
    {
        TrackingTimeline::create([
            'initiative_id' => $initiative->id, 'event_type' => $event,
            'description' => $description ?? $code->tracking_code, 'actor_id' => auth()->id(),
            'metadata' => ['tracking_code' => $code->tracking_code, 'tracking_code_id' => $code->id],
            'occurred_at' => now(),
        ]);
    }

    public function downloadPdf(TrackingCode $trackingCode)
    {
        $this->authorizeTaskRead($trackingCode);
        if (! $trackingCode->order_pdf_path || ! Storage::disk('local')->exists($trackingCode->order_pdf_path)) {
            abort(404, 'PDF Document not found.');
        }

        return Storage::disk('local')->download($trackingCode->order_pdf_path, $trackingCode->tracking_code.'_Order.pdf');
    }

    protected function getInitiativeAllowedLocations(Initiative $initiative)
    {
        return Location::withoutGlobalScopes()
            ->where('company_id', $initiative->owner_company_id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Only the area already chosen. The region picker searches gov.geo.search, because the
     * full area table (~178k rows with mauzas) does not fit in a request's memory.
     */
    private function selectedGeoAreas(mixed $geoAreaId)
    {
        if (! class_exists(GeoArea::class) || ! is_numeric($geoAreaId)) {
            return collect();
        }

        return GeoArea::whereKey((int) $geoAreaId)->get(['GeoAreaId', 'geo_type', 'en_name', 'bn_name']);
    }

    private function assertTask(Initiative $initiative, TrackingCode $task, bool $lock = false): TrackingCode
    {
        abort_unless((int) $task->initiative_id === (int) $initiative->id, 404);
        if ($lock) {
            $task = TrackingCode::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $initiative = Initiative::whereKey($initiative->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($initiative->status, ['Planning', 'Active'], true), 409);
        }

        return $task;
    }

    private function authorizeTaskRead(TrackingCode $task): void
    {
        $initiative = Initiative::find($task->initiative_id);
        if ($initiative) {
            $this->authService->authorize($initiative, ['HEAD', 'OFFICER', 'SUPPORT', 'MONITOR']);

            return;
        }
        $context = app(TenantContext::class);
        app(ProgrammeVerifier::class)->resolve($task->tracking_code, (int) $context->locationId);
    }

    private function validateTargets(Request $request, Initiative $initiative, string $specificity, array $rules): void
    {
        $rules['geo_area_id'] = 'nullable|required_if:geo_override,GeoArea|integer|exists:gov_geo_areas,GeoAreaId';
        if ($specificity === '2_CATEGORY') {
            $rules += ['targets' => 'required|array|min:1', 'targets.*.category_id' => 'required|integer|distinct|exists:categories,id',
                'targets.*.planned_qty' => 'required|integer|min:1', 'targets.*.economic_code' => 'nullable|string|max:50'];
        }
        if ($specificity === '3_MATRIX') {
            $rules += ['matrix_categories' => 'required|array|min:1', 'matrix_categories.*' => 'required|integer|distinct|exists:categories,id',
                'matrix_locations' => 'required|array|min:1', 'matrix_locations.*' => 'required|integer|distinct|exists:locations,id',
                'matrix_values' => 'required|array', 'matrix_values.*' => 'array', 'matrix_values.*.*' => 'nullable|integer|min:0',
                'matrix_economic_codes' => 'nullable|array', 'matrix_economic_codes.*' => 'nullable|string|max:50'];
        }
        $request->validate($rules);
        $categoryIds = $specificity === '3_MATRIX' ? $request->input('matrix_categories', [])
            : array_column($request->input('targets', []), 'category_id');
        abort_unless(Category::whereIn('id', $categoryIds)->count() === count($categoryIds), 404);
        $fund = FundingType::findOrFail($request->integer('funding_type_id'));
        if ($fund->primary_type !== $initiative->primary_funding) {
            throw ValidationException::withMessages(['funding_type_id' => __('govtracking::general.invalid_input')]);
        }
        if ($specificity === '3_MATRIX') {
            $probe = new TrackingCode;
            $probe->setRelation('initiative', $initiative);
            $scopes = collect([
                new TrackingScope(['dimension' => 'GEOGRAPHY', 'target_type' => $request->input('geo_override'), 'target_id' => $request->input('geo_area_id')]),
                new TrackingScope(['dimension' => 'PARTICIPANTS', 'target_type' => $request->input('participant_override')]),
            ]);
            foreach ($request->input('matrix_locations') as $id) {
                $probe->setRelation('scopes', $scopes);
                $check = app(ScopeValidatorService::class)->validateExecutionScope($probe, (int) $id);
                if (! $check['is_valid']) {
                    throw ValidationException::withMessages(['matrix_locations' => $check['message']]);
                }
            }
        }
    }
}
