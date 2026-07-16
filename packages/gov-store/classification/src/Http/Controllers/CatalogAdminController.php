<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use GovStore\Classification\Services\CatalogDatasetLocator;
use GovStore\Classification\Services\CatalogImportCoordinator;
use GovStore\Classification\Services\CatalogImportService;
use Throwable;

class CatalogAdminController extends Controller
{
    protected CatalogDatasetLocator $locator;
    protected CatalogImportCoordinator $coordinator;
    protected CatalogImportService $searchService;

    public function __construct(
        CatalogDatasetLocator $locator, 
        CatalogImportCoordinator $coordinator,
        CatalogImportService $searchService
    ) {
        $this->locator = $locator;
        $this->coordinator = $coordinator;
        $this->searchService = $searchService;
    }

    public function importForm()
    {
        return view('gov-classification::manager.import', ['step' => 1]);
    }

    /**
     * STEP 2: Handle Validation / Review (Optional)
     */
    /**
     * STEP 2: Handle Uploads and Run the Diff Analysis
     */
    public function importValidate(Request $request, CatalogImportService $importer)
    {
        $request->validate([
            'scheme'  => 'required|string',
            'version' => 'required|string',
            'source'  => 'required|string'
        ]);

        try {
            $source = $request->input('source');
            $scheme = $request->input('scheme');
            $version = $request->input('version');

            if ($source === 'bundle') {
                $metaPath = $this->resolveBundledPath('compiled_nodes.csv');
                $treePath = null;
            } else {
                $request->validate([
                    'metadata_file' => 'required|file|mimes:csv,txt',
                    'tree_file'     => 'nullable|file|mimes:csv,txt'
                ]);

                $metaPath = $request->file('metadata_file')->store('tmp/catalog_imports');
                $treePath = $request->hasFile('tree_file') ? $request->file('tree_file')->store('tmp/catalog_imports') : null;
            }

            // Run Diff Analysis
            $absMeta = ($source === 'bundle') ? $metaPath : storage_path("app/{$metaPath}");
            $report = $importer->analyzeDiff($absMeta, $scheme);

            return view('gov-classification::manager.import', [
                'step'     => 2,
                'scheme'   => $scheme,
                'version'  => $version,
                'metaPath' => $metaPath,
                'treePath' => $treePath,
                'source'   => $source,
                'report'   => $report
            ]);
            
        } catch (\Throwable $e) {
            return redirect()->route('gov.catalog.import')
                ->with('error', 'Analysis failed: ' . $e->getMessage());
        }
    }

    /**
     * STEP 3: Execute Ingestion
     */
    public function importExecute(Request $request)
    {
        try {
            $scheme = $request->input('scheme');
            $version = $request->input('version');

            // Find compiled paths through the locator service
            $paths = $this->locator->findBundle($scheme, $version);

            // Ingest using constant-memory coordinators
            $results = $this->coordinator->execute($paths, $scheme, $version, auth()->id());

            return view('gov-classification::manager.import', [
                'step'    => 3,
                'results' => $results,
                'scheme'  => $scheme,
                'version' => $version,
            ]);

        } catch (Throwable $e) {
            return redirect()->route('gov.catalog.import')
                ->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function mappingGrid()
    {
        $mappings = DB::table('gov_catalog_snipe_mappings')->paginate(15);
        return view('gov-classification::manager.mapping', compact('mappings'));
    }

    public function externalGrid()
    {
        return view('gov-classification::manager.external');
    }

    public function importHistory()
    {
        $history = DB::table('gov_catalog_import_history')
            ->orderBy('imported_at', 'desc')
            ->paginate(15);
            
        return view('gov-classification::manager.history', compact('history'));
    }
}