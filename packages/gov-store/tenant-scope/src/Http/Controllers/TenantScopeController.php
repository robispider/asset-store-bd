<?php

namespace GovStore\TenantScope\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use GovStore\TenantScope\Models\TenantScopeConfig;
use GovStore\TenantScope\Models\TenantScopeMapping;
use App\Models\Company;
use App\Models\Location;
use App\Models\Category;
use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Models\Supplier;

class TenantScopeController extends Controller
{
    private function checkSuperadminAccess()
    {
        if (!auth()->user()->isSuperUser()) {
            abort(403, 'Unauthorized. Data isolation settings require system superadministrator privileges.');
        }
    }

    public function index()
    {
        $this->checkSuperadminAccess();

        // Load setting lines
        $configs = TenantScopeConfig::all()->keyBy('reference_type');

        // Load active mappings with their polymorphic scopes (using eager loading)
        $mappings = TenantScopeMapping::orderBy('created_at', 'desc')->get();

        return view('govscope::admin.index', compact('configs', 'mappings'));
    }

    public function saveStrategy(Request $request)
    {
        $this->checkSuperadminAccess();

        $request->validate([
            'strategies' => 'required|array'
        ]);

        foreach ($request->input('strategies') as $type => $data) {
            TenantScopeConfig::updateOrCreate(
                ['reference_type' => $type],
                [
                    'scope_strategy' => $data['strategy'],
                    'show_only_used' => isset($data['show_only_used']) ? (bool)$data['show_only_used'] : false
                ]
            );
        }

        // Bust the cached configs so the new strategy takes effect immediately
        // (InitializeTenantContext caches this key for 1 hour).
        Cache::forget('tenant_scope_configs');

        return redirect()->back()->with('success', 'Scoping policies successfully saved.');
    }

    /**
     * AJAX Endpoint: Searches standard reference elements, bypassing the global scopes 
     * during the mapping assignment process using Eloquent's withoutGlobalScopes method!
     */
    public function referenceSearch(Request $request)
    {
        $this->checkSuperadminAccess();
        $term = $request->input('q', '');
        $type = $request->input('type', '');

        if (empty($term) || empty($type)) {
            return response()->json([]);
        }

        $results = [];

        if ($type === 'category') {
            $items = Category::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => "{$item->name} (Category)"];
            }
        } elseif ($type === 'model') {
            $items = AssetModel::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => "{$item->name} (Asset Model)"];
            }
        } elseif ($type === 'manufacturer') {
            $items = Manufacturer::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => "{$item->name} (Manufacturer)"];
            }
        } elseif ($type === 'supplier') {
            $items = Supplier::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => "{$item->name} (Supplier)"];
            }
        }

        return response()->json($results);
    }

    /**
     * AJAX Endpoint: Searches companies or locations to act as the mapping boundary
     */
    public function tenantSearch(Request $request)
    {
        $this->checkSuperadminAccess();
        $term = $request->input('q', '');
        $type = $request->input('type', '');

        if (empty($term) || empty($type)) {
            return response()->json([]);
        }

        $results = [];

        if ($type === 'company') {
            $items = Company::where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => "{$item->name} (Ministry/Company)"];
            }
        } elseif ($type === 'location') {
            $items = Location::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) {
                $results[] = ['id' => $item->id, 'text' => "{$item->name} (Office Location)"];
            }
        }

        return response()->json($results);
    }

    public function storeMapping(Request $request)
    {
        $this->checkSuperadminAccess();

        $request->validate([
            'reference_type' => 'required|string',
            'reference_id'   => 'required|integer',
            'scope_type'     => 'required|string',
            'scope_id'       => 'required|integer',
        ]);

        try {
            // Prevent duplicate mappings
            TenantScopeMapping::firstOrCreate([
                'scope_type'     => $request->scope_type,
                'scope_id'       => $request->scope_id,
                'reference_type' => $request->reference_type,
                'reference_id'   => $request->reference_id,
            ]);

            return redirect()->back()->with('success', 'Geographical or Organizational scope mapped successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroyMapping($id)
    {
        $this->checkSuperadminAccess();

        try {
            $mapping = TenantScopeMapping::findOrFail($id);
            $mapping->delete();

            return redirect()->back()->with('success', 'Scoping boundary rule deleted.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}