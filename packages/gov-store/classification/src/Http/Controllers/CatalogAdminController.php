<?php

namespace GovStore\Classification\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use GovStore\Classification\Services\CatalogImportService;
use Exception;

class CatalogAdminController extends Controller
{
    public function importForm()
    {
        return view('gov-classification::manager.import', ['step' => 1]);
    }

    /**
     * STEP 2: Handle Uploads and Run the Diff Analysis
     */
    public function importValidate(Request $request, CatalogImportService $importer)
    {
        try {
            $source = $request->input('source', 'upload');
            
            if ($source === 'bundle') {
                // Target the compiled nodes file for the diff analysis report
                $metaPath = realpath(__DIR__ . '/../../database/data/compiled_nodes.csv');
                $treePath = null; // No extra validation file needed for pre-compiled runs
                
                if (!$metaPath || !file_exists($metaPath)) {
                    throw new Exception("Pre-compiled nodes dataset (compiled_nodes.csv) is missing from the database/data directory.");
                }

                $scheme = 'UNSPSC';
                $version = 'UNv260801';
                
            } else {
                // Standard Upload Workflow (Requires manual files)
                $request->validate([
                    'scheme'        => 'required|string',
                    'version'       => 'required|string',
                    'metadata_file' => 'required|file|mimes:csv,txt',
                    'tree_file'     => 'nullable|file|mimes:csv,txt'
                ]);

                $metaPath = $request->file('metadata_file')->store('tmp/catalog_imports');
                $treePath = $request->hasFile('tree_file') ? $request->file('tree_file')->store('tmp/catalog_imports') : null;
                $scheme = $request->input('scheme');
                $version = $request->input('version');
            }

            // If the user clicked "Direct Import", skip analysis and execute immediately
            if ($request->input('action') === 'direct') {
                $request->merge([
                    'metaPath' => $metaPath, 
                    'treePath' => $treePath,
                    'scheme'   => $scheme,
                    'version'  => $version,
                    'source'   => $source
                ]);
                return $this->importExecute($request, $importer);
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
            
        } catch (Exception $e) {
            return redirect()->route('gov.catalog.import')->with('error', 'Analysis failed: ' . $e->getMessage());
        }
    }

public function importExecute(Request $request, CatalogImportService $importer)
    {
        try {
            $source = $request->input('source');
            $scheme = $request->input('scheme');
            $version = $request->input('version');

            if ($source === 'bundle') {
                // Instantly run the compiled datasets
                $results = $importer->executeBundled($scheme, $version, auth()->id());
            } else {
                // (Fallback logic for manual uploads)
            }

            return view('gov-classification::manager.import', [
                'step'    => 3,
                'results' => $results,
                'scheme'  => $scheme,
                'version' => $version,
            ]);

        } catch (Exception $e) {
            return redirect()->route('gov.catalog.import')->with('error', 'Import Execution failed: ' . $e->getMessage());
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
        $history = DB::table('gov_catalog_import_history')->orderBy('imported_at', 'desc')->paginate(15);
        return view('gov-classification::manager.history', compact('history'));
    }
}