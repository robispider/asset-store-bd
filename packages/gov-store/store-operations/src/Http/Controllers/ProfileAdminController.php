<?php

namespace GovStore\StoreOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Location;
use GovStore\StoreOperations\Enums\AssignmentScope;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\StoreOperations\Enums\PolicyStatus;
use GovStore\StoreOperations\Models\Profile;
use GovStore\StoreOperations\Models\ProfileAssignment;
use GovStore\StoreOperations\Services\CapabilityRegistry;
use GovStore\StoreOperations\Services\ProfileCompilerService;
use GovStore\StoreOperations\Services\RuleTraceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileAdminController extends Controller
{
    /** Hub views: A = tree + what applies here, B = flow lanes, C = family tree, plus the policy library. */
    private const STUDIO_VIEWS = ['tree', 'flow', 'family', 'policies'];

    /**
     * The Rules studio: one context (office + item) traced through every layer, shown three ways.
     */
    public function index(Request $request, RuleTraceService $trace)
    {
        $view = in_array($request->query('view'), self::STUDIO_VIEWS, true) ? $request->query('view') : 'tree';
        $pick = fn (string $key, string $class) => ctype_digit((string) $request->query($key)) ? $class::find((int) $request->query($key)) : null;
        $location = $pick('location', Location::class);
        $company = $location ? null : $pick('company', Company::class);
        $model = $pick('model', AssetModel::class);
        $category = $model ? null : $pick('category', Category::class);
        if (! $request->hasAny(['location', 'company', 'category', 'model'])) {
            // First visit: the administrator's own office and the first category that carries a policy.
            $context = app(\GovStore\TenantScope\Contexts\TenantContext::class);
            $location = $context->locationId ? Location::find($context->locationId) : null;
            $category = Category::query()->whereIn('id', ProfileAssignment::query()->where('target_type', 'App\Models\Category')
                ->whereNull('effective_to')->select('target_id'))->orderBy('name')->first();
        }

        $result = $trace->trace($company, $location, $category, $model);
        $query = array_filter(['view' => $view, 'company' => $result['company']?->id && ! $result['location'] ? $result['company']->id : null,
            'location' => $result['location']?->id, 'category' => $result['model'] ? null : $result['category']?->id,
            'model' => $result['model']?->id]);
        $rules = $result['rules'];
        $selectedRule = array_key_exists((string) $request->query('rule'), $rules) ? $request->query('rule') : array_key_first(array_filter($rules, fn ($r) => $r['replaced'])) ?? array_key_first($rules);
        $node = $this->studioNode((string) $request->query('node'), $result);

        $locations = Location::query()->orderBy('name')->get(['id', 'name', 'company_id']);
        $categories = Category::query()->whereIn('category_type', ['asset', 'consumable', 'accessory', 'component'])->orderBy('name')->get(['id', 'name', 'category_type'])->groupBy('category_type');
        $models = $result['category'] ? AssetModel::query()->where('category_id', $result['category']->id)->orderBy('name')->get(['id', 'name']) : collect();

        $data = compact('view', 'result', 'query', 'rules', 'selectedRule', 'node', 'locations', 'categories', 'models') + [
            'layers' => $result['layers'],
            'canPublish' => $this->canPublishRules(),
            'publishedProfiles' => Profile::query()->where('status', PolicyStatus::PUBLISHED->value)->orderBy('name')->get(),
        ];
        if (in_array($view, ['tree', 'family'], true)) {
            $data['orgTree'] = $trace->orgTree($result['company']?->id);
            $data['productTree'] = $trace->productTree($result['layers'], $result['model']?->id);
        }
        if ($view === 'policies') {
            $data['draftProfiles'] = Profile::query()->where('status', PolicyStatus::DRAFT->value)->orderBy('name')->get();
            $data['recentActivity'] = ProfileAssignment::with('profile')->latest()->limit(4)->get()
                ->map(fn ($a) => ['policy' => $a->profile?->name ?? '—', 'target' => $trace->targetName($a), 'when' => $a->created_at?->diffForHumans()]);
        }

        return view('storeops::admin.rules.index', $data);
    }

    /** The node whose attached policy the studio can change: ?node=type:id, else the deepest chosen level. */
    private function studioNode(string $requested, array $result): array
    {
        [$type, $id] = array_pad(explode(':', $requested, 2), 2, null);
        $chosen = ['model' => $result['model'], 'category' => $result['category'], 'location' => $result['location'], 'company' => $result['company']];
        if (! isset($chosen[$type]) && $type !== 'global') {
            $type = array_key_first(array_filter($chosen)) ?? 'global';
        }
        $target = $type === 'global' ? null : $chosen[$type];
        $layer = ['global' => 'GLOBAL', 'company' => 'COMPANY', 'location' => 'LOCATION', 'category' => 'CATEGORY', 'model' => 'MODEL'][$type];
        $traced = collect($result['layers'])->firstWhere('key', $layer);

        return [
            'type' => $type, 'layer' => $layer,
            'name' => $target?->name ?? __('storeops::studio.whole_country'),
            'target_type' => ['global' => 'System', 'company' => 'Tenant', 'location' => 'App\Models\Location', 'category' => 'App\Models\Category', 'model' => 'App\Models\AssetModel'][$type],
            'target_id' => $target?->id ?? 'global',
            'assignment_id' => $traced['assignment_id'] ?? null,
            'profile' => $traced['profile'] ?? null,
        ];
    }

    private function canPublishRules(): bool
    {
        return (bool) auth()->user() && app(\GovStore\TenantScope\Services\GovAccess::class)->decide(auth()->user(), 'storeops.rules.publish')->allowed;
    }

    /**
     * AJAX Endpoint: Renders the "Effective Rules" and Active Assignments.
     */
    public function inspector(Request $request, ProfileCompilerService $compiler)
    {
        $request->validate(['target_type' => 'required|in:GLOBAL,LOCATION,CATEGORY', 'target_id' => 'required']);
        $targetId = $request->input('target_id');
        $targetType = $request->input('target_type'); // GLOBAL, LOCATION, CATEGORY

        $targetName = 'System Global Baseline';
        $productClass = 'App\Models\Category';
        $productId = 0;

        if ($targetType === 'CATEGORY') {
            $cat = Category::findOrFail($targetId);
            $targetName = 'Category: '.$cat->name;
            $productClass = 'App\Models\Category';
            $productId = $cat->id;
        } elseif ($targetType === 'LOCATION') {
            $loc = Location::findOrFail($targetId);
            $targetName = 'Location: '.$loc->name;
            $productId = 0;
        }

        $dbTargetType = $targetType === 'CATEGORY' ? 'App\Models\Category' : ($targetType === 'LOCATION' ? 'App\Models\Location' : 'System');

        $assignments = ProfileAssignment::with('profile')
            // Office layers are stored as 'Location', the key the engine reads.
            ->whereIn('target_type', $dbTargetType === 'App\Models\Location' ? ['Location', $dbTargetType] : [$dbTargetType])
            ->when($targetId !== 'global', fn ($q) => $q->where('target_id', $targetId))
            ->where(function ($query) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', now());
            })
            ->get();

        $previewContext = clone app(\GovStore\TenantScope\Contexts\TenantContext::class);
        if ($targetType === 'LOCATION') {
            $previewContext->locationId = (int) $loc->id;
            $previewContext->companyId = $loc->company_id ? (int) $loc->company_id : null;
        } elseif ($targetType === 'GLOBAL') {
            $previewContext->locationId = $previewContext->companyId = null;
        }
        $previewCompiler = new ProfileCompilerService($previewContext);
        $rawCompiledRules = $targetType === 'CATEGORY' ? $previewCompiler->compileItem($productClass, $productId) : $previewCompiler->compileContext();

        $dictionary = CapabilityRegistry::getDictionary();
        $effectiveRules = [];

        foreach ($dictionary as $code => $dictInfo) {
            $group = $dictInfo['group'];
            $effectiveRules[$group][$code] = [
                'name' => $dictInfo['name'],
                'desc' => $dictInfo['desc'],
                'state' => $rawCompiledRules[$code] ?? ['behavior' => 'INHERIT', 'enforced' => false, 'source_policy' => 'None'],
            ];
        }

        // Fetch published policies so admins can choose one to assign inside this panel
        $publishedProfiles = Profile::where('status', 'PUBLISHED')->orderBy('name')->get();

        return view('storeops::admin.rules.partials.inspector', compact(
            'targetName', 'targetType', 'targetId', 'assignments', 'effectiveRules', 'publishedProfiles', 'dbTargetType'
        ));
    }

    /**
     * Generic Policy Assignment Handler (Supports Global, Company, Location, and Categories)
     */
    public function assignPolicy(Request $request)
    {
        $targets = [
            'System' => null,
            'Tenant' => 'companies',
            'App\\Models\\Location' => 'locations',
            'App\\Models\\Category' => 'categories',
            'App\\Models\\AssetModel' => 'models',
        ];
        $data = $request->validate([
            'target_type' => ['required', 'string', Rule::in(array_keys($targets))],
            'target_id' => ['required', 'regex:/^(global|[1-9][0-9]*)$/'],
            'profile_id' => ['required', 'integer', 'exists:gov_profiles,id'],
        ]);
        abort_if($data['target_type'] === 'System' && $data['target_id'] !== 'global', 422);
        abort_if($data['target_type'] !== 'System' && $data['target_id'] === 'global', 422);
        if ($targets[$data['target_type']]) {
            $request->validate(['target_id' => ['required', 'integer', Rule::exists($targets[$data['target_type']], 'id')]]);
        }
        $profile = Profile::whereKey($data['profile_id'])->lockForUpdate()->firstOrFail();
        abort_unless($profile->status === PolicyStatus::PUBLISHED, 422);

        $now = now();
        $targetId = $data['target_id'] === 'global' ? 1 : (int) $data['target_id'];
        // The engine looks office layers up as 'Location' (ProfileCompilerService::activeLayers).
        $storedType = $data['target_type'] === 'App\Models\Location' ? 'Location' : $data['target_type'];

        // 1. Soft-expire any currently active assignment for this exact target node
        ProfileAssignment::whereIn('target_type', array_unique([$storedType, $data['target_type']]))
            ->where('target_id', $targetId)
            ->whereNull('effective_to')
            ->update(['effective_to' => $now]);

        // 2. Create the new GPO alignment
        $scopeLevel = match ($request->target_type) {
            'Tenant' => AssignmentScope::COMPANY->value,
            'App\Models\Location' => AssignmentScope::LOCATION->value,
            'System' => AssignmentScope::GLOBAL->value,
            default => AssignmentScope::NATIVE->value,
        };

        ProfileAssignment::create([
            'profile_id' => $request->profile_id,
            'target_type' => $storedType,
            'target_id' => $targetId,
            'scope_level' => $scopeLevel,
            'scope_id' => in_array($request->target_type, ['Tenant', 'App\Models\Location'], true) ? $targetId : null,
            'assigned_by' => auth()->id() ?? 1,
            'effective_from' => $now,
        ]);
        ProfileCompilerService::clearResolvedCache();

        return redirect()->back()->with('success', 'Policy successfully assigned.');
    }

    /**
     * Soft-deletes/unassigns an active policy assignment
     */
    public function unassignPolicy($id)
    {
        $assignment = ProfileAssignment::findOrFail($id);
        $assignment->update(['effective_to' => now()]);
        ProfileCompilerService::clearResolvedCache();

        return redirect()->back()->with('success', 'Policy successfully unassigned.');
    }

    /**
     * Renders the Policy Builder Canvas.
     */
    public function editPolicy(Request $request, $id, RuleTraceService $trace)
    {
        $policy = Profile::with('capabilities')->findOrFail($id);

        $dictionary = CapabilityRegistry::getDictionary();
        $existingCaps = $policy->capabilities->keyBy('capability_code');

        // Where the policy (or the published version this draft replaces) is attached decides what
        // reaches it from above. Unattached policies are previewed as a product-category policy,
        // which is where every policy is attached today.
        $attachments = $trace->attachments($policy);
        $anchor = $attachments->first();
        $anchorKey = $anchor ? $trace->layerOf($anchor) : 'CATEGORY';
        $officeMatters = in_array($anchorKey, ['CATEGORY', 'MODEL'], true);
        $office = null;
        if ($officeMatters) {
            $officeId = ctype_digit((string) $request->query('office')) ? (int) $request->query('office') : app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId;
            $office = $officeId ? Location::find($officeId) : null;
        }
        $result = match ($anchorKey) {
            'GLOBAL' => $trace->trace(null, null, null, null),
            'COMPANY' => $trace->trace(Company::find($anchor->target_id), null, null, null),
            'LOCATION' => $trace->trace(null, Location::find($anchor->target_id), null, null),
            'CATEGORY' => $trace->trace(null, $office, $anchor ? Category::find($anchor->target_id) : null, null),
            'MODEL' => $trace->trace(null, $office, null, AssetModel::find($anchor->target_id)),
        };
        $layers = $result['layers'];
        $anchorIndex = array_search($anchorKey, array_column($layers, 'key'), true);
        $above = $trace->fold(array_slice($layers, 0, $anchorIndex));
        $sources = collect($layers)->mapWithKeys(fn ($l) => [$l['key'] => $l['key'] === 'DEFAULT'
            ? __('storeops::studio.package_default') : ($l['profile']['name'] ?? $l['target'])]);

        $groupedRules = [];
        $changes = 0;
        $warnings = 0;
        foreach ($dictionary as $code => $dictInfo) {
            $existing = $existingCaps->get($code);
            $behavior = $existing ? ($existing->behavior instanceof \BackedEnum ? $existing->behavior->value : $existing->behavior) : 'INHERIT';
            $from = $above[$code] ?? null;
            $row = $dictInfo + [
                'code' => $code,
                'behavior' => $behavior,
                'config' => $existing ? (array) ($existing->config_payload ?? []) : [],
                'above' => $from['behavior'] ?? '',
                'above_config' => $from['config'] ?? [],
                'above_label' => $trace->label($code, $from['behavior'] ?? null, $from['config'] ?? []),
                'above_source' => $from ? $sources[$from['layer']] : null,
                'on_label' => $trace->label($code, 'ENFORCE'),
            ];
            $row += $this->editorOutcome($row, $trace);
            $changes += $row['differs'] ? 1 : 0;
            $warnings += $row['tone'] === 'warn' ? 1 : 0;
            $groupedRules[$dictInfo['group']][$code] = $row;
        }

        $chain = [];
        foreach ($layers as $index => $layer) {
            $chain[] = $layer + ['role' => $index < $anchorIndex ? 'above' : ($index === $anchorIndex ? 'this' : 'after')];
        }

        return view('storeops::admin.rules.edit', [
            'policy' => $policy, 'groupedRules' => $groupedRules, 'existingCaps' => $existingCaps,
            'attachedTo' => $attachments->take(6)->map(fn ($a) => ['layer' => $trace->layerOf($a), 'name' => $trace->targetName($a)]),
            'attachedCount' => $attachments->count(), 'anchorKey' => $anchorKey, 'chain' => $chain,
            'officeMatters' => $officeMatters, 'office' => $office, 'changes' => $changes, 'warnings' => $warnings,
            'locations' => $officeMatters ? Location::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'editable' => $policy->status === PolicyStatus::DRAFT && $this->canPublishRules(),
            'canPublish' => $this->canPublishRules(),
        ]);
    }

    /**
     * What the storekeeper gets for one editor row and how to explain it. rules-studio.js repeats
     * this when a choice changes, so keep the two in step.
     */
    private function editorOutcome(array $row, RuleTraceService $trace): array
    {
        $above = $row['above'] ?: null;
        $result = match ($row['behavior']) { 'ENFORCE' => 'ENFORCE', 'DISABLE' => 'DISABLE', default => $above };
        [$note, $tone] = match (true) {
            $row['behavior'] === 'INHERIT' && $above !== null => [__('storeops::studio.note_follows', ['source' => $row['above_source']]), 'info'],
            $row['behavior'] === 'INHERIT' => [__('storeops::studio.note_nobody'), 'info'],
            $row['behavior'] === $above => [__('storeops::studio.note_repeats'), 'info'],
            $row['behavior'] === 'DISABLE' && $above === 'ENFORCE' => [__('storeops::studio.note_overrides', ['source' => $row['above_source']]), 'warn'],
            $row['behavior'] === 'DISABLE' && $above === 'DISABLE' => [__('storeops::studio.note_already_off'), 'info'],
            $row['behavior'] === 'DISABLE' => [__('storeops::studio.note_off_unset'), 'info'],
            $above === 'DISABLE' => [__('storeops::studio.note_turns_back_on', ['source' => $row['above_source']]), 'good'],
            default => [__('storeops::studio.note_adds'), 'good'],
        };

        return [
            'result' => $result ?? '',
            'result_label' => $trace->label($row['code'], $result, match ($row['behavior']) {
                'ENFORCE' => array_merge($row['above_config'], $row['config']), 'INHERIT' => $row['above_config'], default => [],
            }),
            'note' => $note, 'tone' => $tone,
            // Only a change in what the storekeeper gets counts: on vs not on, or other warranty months.
            'differs' => ($result === 'ENFORCE') !== ($above === 'ENFORCE')
                || ($row['behavior'] === 'ENFORCE' && $above === 'ENFORCE' && isset($row['config']['warranty_months'])
                    && (int) $row['config']['warranty_months'] !== (int) ($row['above_config']['warranty_months'] ?? -1)),
        ];
    }

    /**
     * Saves the 3-state toggles and configurations, enforcing DRAFT status.
     */
    public function saveDraftPolicy(Request $request, $id)
    {
        $policy = Profile::whereKey($id)->lockForUpdate()->firstOrFail();

        abort_unless($policy->status === PolicyStatus::DRAFT, 409);
        $policy->update(['status' => PolicyStatus::DRAFT->value]);
        $policy->capabilities()->delete();

        $capabilities = $request->input('rules', []);

        foreach ($capabilities as $code => $data) {
            $behavior = $data['behavior'] ?? 'INHERIT';

            if ($behavior === 'INHERIT') {
                continue;
            }

            $policy->capabilities()->create([
                'capability_code' => $code,
                'behavior' => $behavior,
                'config_payload' => isset($data['config']) ? $data['config'] : null,
            ]);
        }

        return redirect()->back()->with('success', 'Policy draft saved successfully.');
    }

    /**
     * Renders the Simulator Shell (Context Selectors).
     */
    public function simulator()
    {
        $locations = Location::orderBy('name')->get();
        $categories = Category::orderBy('name')->get();

        return view('storeops::admin.rules.simulator', compact('locations', 'categories'));
    }

    /**
     * Runs the Multi-Layer Merge and outputs the side-by-side Mock UI.
     */
    public function runSimulation(Request $request, ProfileCompilerService $compiler)
    {
        $request->validate(['location_id' => 'required|integer|min:1', 'category_id' => 'required|integer|min:1']);
        $locationId = $request->input('location_id');
        $categoryId = $request->input('category_id');

        $location = Location::findOrFail($locationId);
        $category = Category::findOrFail($categoryId);

        $previewContext = clone app(\GovStore\TenantScope\Contexts\TenantContext::class);
        $previewContext->locationId = (int) $location->id;
        $previewContext->companyId = $location->company_id ? (int) $location->company_id : null;
        $rawCompiledRules = (new ProfileCompilerService($previewContext))->compileItem('App\Models\Category', (int) $categoryId);

        $dictionary = CapabilityRegistry::getDictionary();
        $simulatedUI = [];
        $automations = [];

        foreach ($dictionary as $code => $dictInfo) {
            $meta = $rawCompiledRules[$code] ?? ['behavior' => 'INHERIT', 'enforced' => false];

            if (isset($meta['enforced']) && $meta['enforced'] === true) {
                $payload = [
                    'name' => $dictInfo['name'],
                    'config' => $meta['config'] ?? [],
                    'source' => $meta['source_policy'] ?? 'Unknown Policy',
                    'layer' => $meta['layer'] ?? 'Unknown Layer',
                    'group' => $dictInfo['group'] ?? 'General',
                ];

                // FIXED: Check if the dictionary group contains "Automation" instead of looking for "type"
                $isAutomation = isset($dictInfo['group']) && str_contains(strtolower($dictInfo['group']), 'automation');

                if ($isAutomation || in_array($code, ['post_inventory', 'create_assets'])) {
                    $automations[$code] = $payload;
                } else {
                    $simulatedUI[$code] = $payload;
                }
            }
        }

        return view('storeops::admin.rules.partials.simulation-result', compact(
            'location', 'category', 'simulatedUI', 'automations'
        ));
    }

    /**
     * AJAX Endpoint: Calculates the exact operational "Blast Radius" of a policy.
     */
    public function getImpactAnalysis($id)
    {
        $policy = Profile::findOrFail($id);

        $assignedCategoryIds = ProfileAssignment::where('profile_id', $id)
            ->where('target_type', 'App\Models\Category')
            ->pluck('target_id')
            ->toArray();

        $categoryCount = count($assignedCategoryIds);

        $affectedModelIds = \DB::table('models')
            ->whereIn('category_id', $assignedCategoryIds)
            ->pluck('id')
            ->toArray();

        $affectedDraftsCount = \DB::table('gov_documents')
            ->join('gov_document_items', 'gov_documents.id', '=', 'gov_document_items.document_id')
            ->where('gov_documents.status', DocumentState::DRAFT->value)
            ->where(function ($query) use ($assignedCategoryIds, $affectedModelIds) {
                $query->where(function ($q) use ($affectedModelIds) {
                    $q->whereIn('gov_document_items.product_type', ['assetmodel', 'asset_model'])
                        ->whereIn('gov_document_items.product_id', $affectedModelIds);
                })
                    ->orWhere(function ($q) use ($assignedCategoryIds) {
                        $q->whereIn('gov_document_items.product_type', ['consumable', 'accessory', 'component'])
                            ->whereIn('gov_document_items.product_id', function ($sub) use ($assignedCategoryIds) {
                                $sub->select('id')->from('consumables')->whereIn('category_id', $assignedCategoryIds)
                                    ->union(
                                        $sub->newQuery()->select('id')->from('accessories')->whereIn('category_id', $assignedCategoryIds)
                                    )->union(
                                        $sub->newQuery()->select('id')->from('components')->whereIn('category_id', $assignedCategoryIds)
                                    );
                            });
                    });
            })
            ->distinct()
            ->count('gov_documents.id');

        return response()->json([
            'categories_affected' => $categoryCount,
            'drafts_affected' => $affectedDraftsCount,
            'risk_level' => $affectedDraftsCount > 5 ? 'HIGH' : ($affectedDraftsCount > 0 ? 'MEDIUM' : 'LOW'),
        ]);
    }

    /**
     * Performs atomic promotion of Draft -> Published and archives the previous version.
     */
    public function publishPolicy(Request $request, $id)
    {
        $draftPolicy = Profile::findOrFail($id);

        if ($draftPolicy->status !== PolicyStatus::DRAFT) {
            return back()->with('error', 'Only draft policies can be published.');
        }

        \DB::transaction(function () use ($draftPolicy) {
            $draftPolicy = Profile::whereKey($draftPolicy->id)->lockForUpdate()->firstOrFail();
            abort_unless($draftPolicy->status === PolicyStatus::DRAFT, 409);
            Profile::where('lineage_id', $draftPolicy->lineage_id)
                ->where('id', '!=', $draftPolicy->id)
                ->where('status', PolicyStatus::PUBLISHED->value)
                ->update([
                    'status' => PolicyStatus::ARCHIVED->value,
                ]);

            $currentVersion = (float) ($draftPolicy->version ?? 1.0);
            $newVersion = number_format($currentVersion + 1.0, 1);

            $draftPolicy->update([
                'status' => PolicyStatus::PUBLISHED->value,
                'published_by' => auth()->id(), 'published_at' => now(), 'publish_reason' => request('change_reason'),
                'version' => $newVersion,
            ]);
            ProfileAssignment::whereIn('profile_id', Profile::where('lineage_id', $draftPolicy->lineage_id)
                ->where('id', '!=', $draftPolicy->id)->select('id'))
                ->whereNull('effective_to')->update(['profile_id' => $draftPolicy->id]);
        });
        ProfileCompilerService::clearResolvedCache();

        return redirect()->route('storeops.admin.rules.index')
            ->with('success', "Policy [{$draftPolicy->name}] promoted successfully.");
    }

    /**
     * Unified Search API: Scans Categories, Locations, and Policies concurrently.
     */
    public function searchApi(Request $request)
    {
        $query = $request->input('q');

        if (empty($query) || strlen($query) < 2) {
            return response()->json([
                'categories' => [],
                'locations' => [],
                'policies' => [],
            ]);
        }

        // 1. Search Category targets
        $categories = Category::where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name', 'category_type'])
            ->map(function ($cat) {
                $icon = $cat->category_type === 'asset' ? 'fa-laptop' : 'fa-tint';

                return [
                    'id' => $cat->id,
                    'type' => 'CATEGORY',
                    'name' => $cat->name.' ('.ucfirst($cat->category_type).')',
                    'icon' => $icon,
                ];
            });

        // 2. Search Location targets
        $locations = Location::where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn ($loc) => [
                'id' => $loc->id,
                'type' => 'LOCATION',
                'name' => $loc->name,
                'icon' => 'fa-building-o',
            ]);

        // 3. Search Published Policies
        $policies = Profile::where('name', 'LIKE', "%{$query}%")
            ->where('status', PolicyStatus::PUBLISHED->value)
            ->limit(5)
            ->get(['id', 'name', 'version'])
            ->map(fn ($pol) => [
                'id' => $pol->id,
                'type' => 'POLICY',
                'name' => $pol->name.' (v'.($pol->version ?? '1.0').')',
                'icon' => 'fa-shield',
            ]);

        return response()->json([
            'categories' => $categories,
            'locations' => $locations,
            'policies' => $policies,
        ]);
    }

    /**
     * Renders the 3-Step Guided Policy Creation Wizard.
     */
    public function createRule($template)
    {
        $template = strtolower($template);
        if (! in_array($template, ['hardware', 'consumable', 'blank'])) {
            abort(404, 'Template not found.');
        }

        // Define what rules will be visually previewed in Step 3 based on template type
        $previewRules = match ($template) {
            'hardware' => [
                'require_quantity' => 'enabled',
                'require_serial' => 'enabled',
                'create_assets' => 'enabled',
            ],
            'consumable' => [
                'require_quantity' => 'enabled',
                'post_inventory' => 'enabled',
            ],
            'blank' => []
        };

        return view('storeops::admin.rules.create', compact('template', 'previewRules'));
    }

    /**
     * Processes wizard form submission, cloning the baseline template capabilities.
     */
    public function storeRule(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:gov_profiles,name',
            'description' => 'nullable|string|max:250',
            'template' => 'required|in:hardware,consumable,blank',
        ]);

        $policy = \DB::transaction(function () use ($request) {
            // 1. Create the new GPO Policy Document in Draft state
            $policy = Profile::create([
                'name' => $request->name,
                'lineage_id' => (string) \Illuminate\Support\Str::uuid(),
                'status' => PolicyStatus::DRAFT->value,
                'version' => '1.0',
                'scope' => 'GLOBAL', // Separated from deployment: scope is assigned later
            ]);

            // 2. Clone baseline capabilities dynamically based on chosen template
            $templateType = strtolower($request->template);

            if ($templateType === 'hardware') {
                $policy->capabilities()->createMany([
                    ['capability_code' => 'require_quantity', 'behavior' => 'ENFORCE'],
                    ['capability_code' => 'require_serial', 'behavior' => 'ENFORCE'],
                    ['capability_code' => 'create_assets', 'behavior' => 'ENFORCE'],
                ]);
            } elseif ($templateType === 'consumable') {
                $policy->capabilities()->createMany([
                    ['capability_code' => 'require_quantity', 'behavior' => 'ENFORCE'],
                    ['capability_code' => 'post_inventory', 'behavior' => 'ENFORCE'],
                ]);
            }

            return $policy;
        });

        // Redirect directly to the Post-Creation Confirmation Hub!
        return redirect()->route('storeops.admin.rules.policies.confirmation', $policy->id);
    }

    /**
     * Post-Creation Confirmation Hub (Provides Closure & Clear Paths)
     */
    public function confirmationHub($id)
    {
        $policy = Profile::with('capabilities')->findOrFail($id);

        return view('storeops::admin.rules.confirmation', compact('policy'));
    }

    /**
     * Atomic Duplication Engine (Clones a policy and its exact behaviors)
     */
    public function duplicateRule($id)
    {
        $original = Profile::with('capabilities')->findOrFail($id);

        $copy = \DB::transaction(function () use ($original) {
            // 1. Clone the parent document, appending "Copy"
            $copyName = $original->name.' - Copy';

            // Handle unique constraint edge cases dynamically
            $count = Profile::where('name', 'LIKE', $copyName.'%')->count();
            if ($count > 0) {
                $copyName .= ' ('.($count + 1).')';
            }

            $copy = Profile::create([
                'name' => $copyName,
                'lineage_id' => $original->lineage_id,
                'status' => PolicyStatus::DRAFT->value,
                'version' => '1.0',
                'scope' => $original->scope,
                'company_id' => $original->company_id,
                'location_id' => $original->location_id,
            ]);

            // 2. Symmetrically clone all associated rules/behaviors
            foreach ($original->capabilities as $cap) {
                $copy->capabilities()->create([
                    'capability_code' => $cap->capability_code,
                    'behavior' => $cap->behavior,
                    'config_payload' => $cap->config_payload,
                ]);
            }

            return $copy;
        });

        return redirect()->route('storeops.admin.rules.policies.confirmation', $copy->id)
            ->with('success', "Policy duplicated successfully as [{$copy->name}]");
    }
}
