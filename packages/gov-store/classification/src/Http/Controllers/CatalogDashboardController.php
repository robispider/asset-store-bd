<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CatalogDashboardController extends Controller
{
    /**
     * Display the Global Catalog dashboard with aggregate statistics.
     */
    public function index()
    {
        $totalNodes = DB::table('gov_catalog_nodes')->count();
        $totalMappings = DB::table('gov_catalog_snipe_mappings')->count();
        $latestImport = DB::table('gov_catalog_import_history')
            ->orderBy('imported_at', 'desc')
            ->first();

        return view('gov-classification::dashboard.index', compact(
            'totalNodes',
            'totalMappings',
            'latestImport'
        ));
    }
}
