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

    public function importValidate(Request $request, CatalogImportService $importer)
    {
        try {
            $source = $request->input('source', 'upload');
            
            if ($source === 'bundle') {
                // Safely resolve the bundled files from the package directory
                $metaPath = realpath(__DIR__ . '/../../database/data/unspsc-english-v260801.csv');
                $treePath = realpath(__DIR__ . '/../../database/data/UNGM_UNSPSC_15-Jul-2026..csv');
                
                if (!$metaPath || !$treePath) {
                    throw new Exception("Bundled datasets are missing from the package directory.");
                }

                $scheme = 'UNSPSC';
                $version = 'UNv260801';
                
            } else {
                // Standard Upload Workflow
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
            // For uploads, we need the absolute path from storage. Bundled is already absolute.
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
                $absMeta = realpath(__DIR__ . '/../../database/data/unspsc-english-v260801.csv');
                $absTree = realpath(__DIR__ . '/../../database/data/UNGM_UNSPSC_15-Jul-2026..csv');
                
                // Safety Guard: Ensure the bundled files actually exist
                if (!$absMeta || !file_exists($absMeta)) {
                    throw new Exception("The bundled Metadata CSV could not be found at the expected path.");
                }
            } else {
                $absMeta = storage_path("app/" . $request->input('metaPath'));
                $absTree = $request->input('treePath') ? storage_path("app/" . $request->input('treePath')) : null;
            }

            // Execute the streaming pipeline
            $results = $importer->execute($absMeta, $absTree, $scheme, $version, auth()->id());

            // Cleanup only if files were temporarily uploaded
            if ($source === 'upload') {
                Storage::delete([$request->input('metaPath')]);
                if ($request->input('treePath')) {
                    Storage::delete([$request->input('treePath')]);
                }
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