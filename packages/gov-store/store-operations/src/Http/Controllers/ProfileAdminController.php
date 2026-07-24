<?php

namespace GovStore\StoreOperations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GovStore\StoreOperations\Models\Profile;
use GovStore\StoreOperations\Models\ProfileAssignment;
use GovStore\StoreOperations\Services\ProfileCompilerService;
use GovStore\StoreOperations\Services\CapabilityRegistry;
use App\Models\Category;
use App\Models\Location;

class ProfileAdminController extends Controller
{
     /**
     * Renders the primary Target Assignment Matrix.
     */
    public function index()
    {
        // 1. Gather database counts for the Quick Access metrics
        $counts = [
            'categories' => Category::count(),
            'locations'  => Location::count(),
            'policies'   => Profile::where('status', 'PUBLISHED')->count(),
        ];

        // 2. Fetch the latest GPO assignments to show as an Activity Feed on the Hub
        $recentActivity = ProfileAssignment::with(['profile', 'target'])
            ->orderBy('created_at', 'desc')
            ->limit(4)
            ->get()
            ->map(function($assign) {
                // Safely resolve the target name
                $targetName = $assign->target ? ($assign->target->name ?? 'Unknown Target') : 'System';
                return [
                    'policy_name' => $assign->profile->name,
                    'target_name' => $targetName,
                    'operator'    => 'System Admin',
                    'date'        => $assign->created_at->diffForHumans(),
                ];
            });

        // 3. Keep the simple tree elements for sidebar directories
        $tree = [
            'Locations (Offices)' => Location::orderBy('name')->limit(15)->get()->map(function($loc) {
                return ['id' => $loc->id, 'type' => 'LOCATION', 'name' => $loc->name, 'icon' => 'fa-building'];
            })->toArray(),
            'Hardware Categories' => Category::where('category_type', 'asset')->orderBy('name')->get()->map(function($cat) {
                return ['id' => $cat->id, 'type' => 'CATEGORY', 'name' => $cat->name, 'icon' => 'fa-laptop'];
            })->toArray(),
            'Consumable Categories' => Category::where('category_type', 'consumable')->orderBy('name')->get()->map(function($cat) {
                return ['id' => $cat->id, 'type' => 'CATEGORY', 'name' => $cat->name, 'icon' => 'fa-tint'];
            })->toArray(),
        ];

        $publishedProfiles = Profile::where('status', 'PUBLISHED')->orderBy('name')->get();

        return view('storeops::admin.rules.index', compact('tree', 'publishedProfiles', 'counts', 'recentActivity'));
    }


    /**
     * AJAX Endpoint: Renders the "Effective Rules" and Active Assignments.
     */
    public function inspector(Request $request, ProfileCompilerService $compiler)
    {
        $targetId = $request->input('target_id');
        $targetType = $request->input('target_type'); // GLOBAL, LOCATION, CATEGORY

        $targetName = "System Global Baseline";
        $productClass = 'App\Models\Category'; 
        $productId = 0;

        if ($targetType === 'CATEGORY') {
            $cat = Category::findOrFail($targetId);
            $targetName = "Category: " . $cat->name;
            $productClass = 'App\Models\Category';
            $productId = $cat->id;
        } elseif ($targetType === 'LOCATION') {
            $loc = Location::findOrFail($targetId);
            $targetName = "Location: " . $loc->name;
            $productId = 0; 
        }

        $dbTargetType = $targetType === 'CATEGORY' ? 'App\Models\Category' : ($targetType === 'LOCATION' ? 'App\Models\Location' : 'System');
        
        $assignments = ProfileAssignment::with('profile')
            ->where('target_type', $dbTargetType)
            ->when($targetId !== 'global', fn($q) => $q->where('target_id', $targetId))
            ->where(function ($query) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', now());
            })
            ->get();

        $rawCompiledRules = $compiler->compileItem($productClass, $productId);
        
        $dictionary = CapabilityRegistry::getDictionary();
        $effectiveRules = [];

        foreach ($dictionary as $code => $dictInfo) {
            $group = $dictInfo['group'];
            $effectiveRules[$group][$code] = [
                'name' => $dictInfo['name'],
                'desc' => $dictInfo['desc'],
                'state' => $rawCompiledRules[$code] ?? ['behavior' => 'INHERIT', 'enforced' => false, 'source_policy' => 'None']
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
        $request->validate([
            'target_type' => 'required|string',
            'target_id'   => 'required', // Can be numeric or string 'global'
            'profile_id'  => 'required|integer'
        ]);

        $now = now();
        $targetId = $request->target_id === 'global' ? 1 : (int) $request->target_id;

        // 1. Soft-expire any currently active assignment for this exact target node
        ProfileAssignment::where('target_type', $request->target_type)
            ->where('target_id', $targetId)
            ->whereNull('effective_to')
            ->update(['effective_to' => $now]);

        // 2. Create the new GPO alignment
        $scopeLevel = match($request->target_type) {
            'App\Models\Location' => \GovStore\StoreOperations\Enums\AssignmentScope::LOCATION->value,
            'System'              => \GovStore\StoreOperations\Enums\AssignmentScope::GLOBAL->value,
            default               => \GovStore\StoreOperations\Enums\AssignmentScope::NATIVE->value,
        };

        ProfileAssignment::create([
            'profile_id'     => $request->profile_id,
            'target_type'    => $request->target_type,
            'target_id'      => $targetId,
            'scope_level'    => $scopeLevel,
            'scope_id'       => $request->target_type === 'App\Models\Location' ? $targetId : null,
            'assigned_by'    => auth()->id() ?? 1,
            'effective_from' => $now,
        ]);

        return redirect()->back()->with('success', 'Policy successfully assigned.');
    }

    /**
     * Soft-deletes/unassigns an active policy assignment
     */
    public function unassignPolicy($id)
    {
        $assignment = ProfileAssignment::findOrFail($id);
        $assignment->update(['effective_to' => now()]);

        return redirect()->back()->with('success', 'Policy successfully unassigned.');
    }

    /**
     * Renders the Policy Builder Canvas.
     */
    public function editPolicy($id)
    {
        $policy = Profile::with('capabilities')->findOrFail($id);
        
        $dictionary = CapabilityRegistry::getDictionary();
        $groupedRules = [];
        foreach ($dictionary as $code => $dictInfo) {
            $groupedRules[$dictInfo['group']][$code] = $dictInfo;
        }

        $existingCaps = $policy->capabilities->keyBy('capability_code');

        return view('storeops::admin.rules.edit', compact('policy', 'groupedRules', 'existingCaps'));
    }

    /**
     * Saves the 3-state toggles and configurations, enforcing DRAFT status.
     */
    public function saveDraftPolicy(Request $request, $id)
    {
        $policy = Profile::findOrFail($id);
        
        $policy->update(['status' => \GovStore\StoreOperations\Enums\PolicyStatus::DRAFT->value]);
        $policy->capabilities()->delete();

        $capabilities = $request->input('rules', []);
        
        foreach ($capabilities as $code => $data) {
            $behavior = $data['behavior'] ?? 'INHERIT';
            
            if ($behavior === 'INHERIT') {
                continue; 
            }

            $policy->capabilities()->create([
                'capability_code' => $code,
                'behavior'        => $behavior,
                'config_payload'  => isset($data['config']) ? $data['config'] : null,
            ]);
        }

        return redirect()->back()->with('success', 'Policy draft saved successfully.');
    }

    /**
     * Renders the Simulator Shell (Context Selectors).
     */
    public function simulator()
    {
        $locations = \App\Models\Location::orderBy('name')->get();
        $categories = \App\Models\Category::orderBy('name')->get();
        
        return view('storeops::admin.rules.simulator', compact('locations', 'categories'));
    }

    /**
     * Runs the Multi-Layer Merge and outputs the side-by-side Mock UI.
     */
    public function runSimulation(Request $request, ProfileCompilerService $compiler)
    {
        $locationId = $request->input('location_id');
        $categoryId = $request->input('category_id');

        $location = \App\Models\Location::find($locationId);
        $category = \App\Models\Category::find($categoryId);

        // NOTE: In a production environment, you would briefly swap the TenantContext location 
        // to $locationId before calling compileItem, then swap it back, to simulate another office.
        $rawCompiledRules = $compiler->compileItem('App\Models\Category', $categoryId);
        
        $dictionary = CapabilityRegistry::getDictionary();
        $simulatedUI = [];
        $automations = [];

        foreach ($dictionary as $code => $dictInfo) {
            $meta = $rawCompiledRules[$code] ?? ['behavior' => 'INHERIT', 'enforced' => false];
            
            if (isset($meta['enforced']) && $meta['enforced'] === true) {
                $payload = [
                    'name'   => $dictInfo['name'],
                    'config' => $meta['config'] ?? [],
                    'source' => $meta['source_policy'] ?? 'Unknown Policy',
                    'layer'  => $meta['layer'] ?? 'Unknown Layer',
                    'group'  => $dictInfo['group'] ?? 'General'
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
            ->where('gov_documents.status', \GovStore\StoreOperations\Enums\DocumentState::DRAFT->value)
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
            'drafts_affected'     => $affectedDraftsCount,
            'risk_level'          => $affectedDraftsCount > 5 ? 'HIGH' : ($affectedDraftsCount > 0 ? 'MEDIUM' : 'LOW'),
        ]);
    }

    /**
     * Performs atomic promotion of Draft -> Published and archives the previous version.
     */
    public function publishPolicy(Request $request, $id)
    {
        $draftPolicy = Profile::findOrFail($id);

        if ($draftPolicy->status !== \GovStore\StoreOperations\Enums\PolicyStatus::DRAFT) {
            return back()->with('error', 'Only draft policies can be published.');
        }

        \DB::transaction(function () use ($draftPolicy) {
            Profile::where('name', $draftPolicy->name)
                ->where('id', '!=', $draftPolicy->id)
                ->where('status', \GovStore\StoreOperations\Enums\PolicyStatus::PUBLISHED->value)
                ->update([
                    'status' => \GovStore\StoreOperations\Enums\PolicyStatus::ARCHIVED->value
                ]);

            $currentVersion = (float) ($draftPolicy->version ?? 1.0);
            $newVersion = number_format($currentVersion + 1.0, 1);

            $draftPolicy->update([
                'status'  => \GovStore\StoreOperations\Enums\PolicyStatus::PUBLISHED->value,
                'version' => $newVersion
            ]);
        });

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
                'locations'  => [],
                'policies'   => []
            ]);
        }

        // 1. Search Category targets
        $categories = \App\Models\Category::where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name', 'category_type'])
            ->map(function($cat) {
                $icon = $cat->category_type === 'asset' ? 'fa-laptop' : 'fa-tint';
                return [
                    'id'   => $cat->id,
                    'type' => 'CATEGORY',
                    'name' => $cat->name . ' (' . ucfirst($cat->category_type) . ')',
                    'icon' => $icon
                ];
            });

        // 2. Search Location targets
        $locations = \App\Models\Location::where('name', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get(['id', 'name'])
            ->map(fn($loc) => [
                'id'   => $loc->id,
                'type' => 'LOCATION',
                'name' => $loc->name,
                'icon' => 'fa-building-o'
            ]);

        // 3. Search Published Policies
        $policies = Profile::where('name', 'LIKE', "%{$query}%")
            ->where('status', \GovStore\StoreOperations\Enums\PolicyStatus::PUBLISHED->value)
            ->limit(5)
            ->get(['id', 'name', 'version'])
            ->map(fn($pol) => [
                'id'   => $pol->id,
                'type' => 'POLICY',
                'name' => $pol->name . ' (v' . ($pol->version ?? '1.0') . ')',
                'icon' => 'fa-shield'
            ]);

        return response()->json([
            'categories' => $categories,
            'locations'  => $locations,
            'policies'   => $policies
        ]);
    }
    /**
     * Renders the 3-Step Guided Policy Creation Wizard.
     */
    public function createRule($template)
    {
        $template = strtolower($template);
        if (!in_array($template, ['hardware', 'consumable', 'blank'])) {
            abort(404, 'Template not found.');
        }

        // Define what rules will be visually previewed in Step 3 based on template type
        $previewRules = match($template) {
            'hardware' => [
                'Require Quantity Enforcements' => '🟢 Enabled (Standard Baseline)',
                'Require Unique Serial Numbers' => '🟢 Enabled (Standard Baseline)',
                'Register Serialized Units as Individual Assets' => '🟢 Enabled (Standard Baseline)',
                'Allow Bulk Inventory Entries' => '🔴 Disabled (Explicitly Blocked)',
            ],
            'consumable' => [
                'Require Quantity Enforcements' => '🟢 Enabled (Standard Baseline)',
                'Write Quantities directly to Stock Ledger' => '🟢 Enabled (Standard Baseline)',
            ],
            'blank' => [
                'All capabilities will be set to: Not Configured (Inherit)' => '⚪ Inherited'
            ]
        };

        return view('storeops::admin.rules.create', compact('template', 'previewRules'));
    }

    /**
     * Processes wizard form submission, cloning the baseline template capabilities.
     */
    public function storeRule(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100|unique:gov_profiles,name',
            'description' => 'nullable|string|max:250',
            'template'    => 'required|string'
        ]);

        $policy = \DB::transaction(function () use ($request) {
            // 1. Create the new GPO Policy Document in Draft state
            $policy = Profile::create([
                'name'    => $request->name,
                'status'  => \GovStore\StoreOperations\Enums\PolicyStatus::DRAFT->value,
                'version' => '1.0',
                'scope'   => 'GLOBAL', // Separated from deployment: scope is assigned later
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
            $copyName = $original->name . ' - Copy';
            
            // Handle unique constraint edge cases dynamically
            $count = Profile::where('name', 'LIKE', $copyName . '%')->count();
            if ($count > 0) {
                $copyName .= ' (' . ($count + 1) . ')';
            }

            $copy = Profile::create([
                'name'        => $copyName,
                'status'      => \GovStore\StoreOperations\Enums\PolicyStatus::DRAFT->value,
                'version'     => '1.0',
                'scope'       => $original->scope,
                'company_id'  => $original->company_id,
                'location_id' => $original->location_id,
            ]);

            // 2. Symmetrically clone all associated rules/behaviors
            foreach ($original->capabilities as $cap) {
                $copy->capabilities()->create([
                    'capability_code' => $cap->capability_code,
                    'behavior'        => $cap->behavior,
                    'config_payload'  => $cap->config_payload,
                ]);
            }

            return $copy;
        });

        return redirect()->route('storeops.admin.rules.policies.confirmation', $copy->id)
            ->with('success', "Policy duplicated successfully as [{$copy->name}]");
    }
}