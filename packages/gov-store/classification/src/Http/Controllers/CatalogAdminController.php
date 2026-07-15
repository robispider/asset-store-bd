<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class CatalogAdminController extends Controller
{
    /**
     * Display the catalog import form.
     */
    public function importForm()
    {
        return view('gov-classification::manager.import');
    }

    /**
     * Display the category mapping grid.
     */
    public function mappingGrid()
    {
        $mappings = DB::table('gov_catalog_snipe_mappings')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('gov-classification::manager.mapping', compact('mappings'));
    }

    /**
     * Display the external mappings grid.
     */
    public function externalGrid()
    {
        return view('gov-classification::manager.external');
    }

    /**
     * Display the import history log.
     */
    public function importHistory()
    {
        $history = DB::table('gov_catalog_import_history')
            ->orderBy('imported_at', 'desc')
            ->paginate(15);

        return view('gov-classification::manager.history', compact('history'));
    }
}
