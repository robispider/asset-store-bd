<?php

namespace GovStore\TenantScope\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
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
        if (!auth()->user()->isSuperUser() && !auth()->user()->hasAccess('admin')) {
            abort(403, 'Unauthorized. Data isolation settings require system superadministrator privileges.');
        }
    }

    // ==========================================
    // WORKSPACE 1: DASHBOARD
    // ==========================================
    public function dashboard()
    {
        $this->checkSuperadminAccess();
        
        $stats = [
            'total_mappings' => TenantScopeMapping::count(),
            'active_configs' => TenantScopeConfig::count(),
            'company_scopes' => TenantScopeMapping::where('scope_type', 'company')->count(),
            'location_scopes' => TenantScopeMapping::where('scope_type', 'location')->count(),
        ];

        // Fetch recent activity
        $recentMappings = TenantScopeMapping::orderBy('created_at', 'desc')->limit(5)->get();

        return view('govscope::admin.dashboard', compact('stats', 'recentMappings'));
    }

    // ==========================================
    // WORKSPACE 2: GLOBAL CONFIGURATOR
    // ==========================================
    public function config()
    {
        $this->checkSuperadminAccess();
        $configs = TenantScopeConfig::all()->keyBy('reference_type');
        return view('govscope::admin.config', compact('configs'));
    }

    public function saveStrategy(Request $request)
    {
        $this->checkSuperadminAccess();
        $request->validate(['strategies' => 'required|array']);

        foreach ($request->input('strategies') as $type => $data) {
            TenantScopeConfig::updateOrCreate(
                ['reference_type' => $type],
                [
                    'scope_strategy' => $data['strategy'],
                    'show_only_used' => isset($data['show_only_used']) ? (bool)$data['show_only_used'] : false
                ]
            );
        }

        return redirect()->back()->with('success', 'Global Scoping Policies successfully saved.');
    }

    // ==========================================
    // WORKSPACE 3: BOUNDARY EXPLORER (Paginated & Filtered)
    // ==========================================
    public function explorer(Request $request)
    {
        $this->checkSuperadminAccess();

        $query = TenantScopeMapping::query();

        // Apply URL Search Filters dynamically
        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }
        if ($request->filled('scope_type')) {
            $query->where('scope_type', $request->scope_type);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Paginates records at database-level (Safe for 10,000+ entries)
        $mappings = $query->orderBy('created_at', 'desc')->paginate(50)->withQueryString();

        return view('govscope::admin.explorer', compact('mappings'));
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
            TenantScopeMapping::firstOrCreate([
                'scope_type'     => $request->scope_type,
                'scope_id'       => $request->scope_id,
                'reference_type' => $request->reference_type,
                'reference_id'   => $request->reference_id,
            ]);

            return redirect()->back()->with('success', 'Data isolation boundary mapped successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function destroyMapping($id)
    {
        $this->checkSuperadminAccess();
        try {
            TenantScopeMapping::findOrFail($id)->delete();
            return redirect()->back()->with('success', 'Scoping boundary rule permanently deleted.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // ==========================================
    // AJAX SELECTORS (Unchanged)
    // ==========================================
    public function referenceSearch(Request $request)
    {
        $this->checkSuperadminAccess();
        $term = $request->input('q', '');
        $type = $request->input('type', '');

        if (empty($term) || empty($type)) return response()->json([]);
        $results = [];

        if ($type === 'category') {
            $items = Category::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) $results[] = ['id' => $item->id, 'text' => "{$item->name} (Category)"];
        } elseif ($type === 'model') {
            $items = AssetModel::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) $results[] = ['id' => $item->id, 'text' => "{$item->name} (Asset Model)"];
        } elseif ($type === 'manufacturer') {
            $items = Manufacturer::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) $results[] = ['id' => $item->id, 'text' => "{$item->name} (Manufacturer)"];
        } elseif ($type === 'supplier') {
            $items = Supplier::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) $results[] = ['id' => $item->id, 'text' => "{$item->name} (Supplier)"];
        }

        return response()->json($results);
    }

    public function tenantSearch(Request $request)
    {
        $this->checkSuperadminAccess();
        $term = $request->input('q', '');
        $type = $request->input('type', '');

        if (empty($term) || empty($type)) return response()->json([]);
        $results = [];

        if ($type === 'company') {
            $items = Company::where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) $results[] = ['id' => $item->id, 'text' => "{$item->name} (Ministry/Company)"];
        } elseif ($type === 'location') {
            $items = Location::withoutGlobalScopes()->where('name', 'like', "%{$term}%")->limit(15)->get();
            foreach ($items as $item) $results[] = ['id' => $item->id, 'text' => "{$item->name} (Office Location)"];
        }

        return response()->json($results);
    }
}