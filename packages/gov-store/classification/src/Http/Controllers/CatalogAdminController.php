<?php

namespace GovStore\Classification\Http\Controllers;

use GovStore\Classification\Services\CatalogDatasetLocator;
use GovStore\Classification\Services\CatalogImportCoordinator;
use GovStore\Classification\Services\CatalogImportService;
use GovStore\Classification\Services\CatalogReview;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $data = $request->validate(['scheme' => 'required|string|max:100', 'version' => 'required|string|max:100', 'source' => 'required|in:bundle']);
        $request->session()->forget('gov_catalog_review');
        try {
            $paths = $this->locator->findBundle($data['scheme'], $data['version']);
            $report = $importer->analyzeDiff($paths['nodes'], $data['scheme']);
            $reviewToken = app(CatalogReview::class)->create($request, $paths, $data['scheme'], $data['version']);

            return view('gov-classification::manager.import', ['step' => 2, 'scheme' => $data['scheme'], 'version' => $data['version'],
                'source' => 'bundle', 'metaPath' => '', 'treePath' => '', 'report' => $report, 'reviewToken' => $reviewToken]);
        } catch (Throwable $e) {
            return $this->safeFailure($e);
        }
    }

    public function importExecute(Request $request)
    {
        $data = $request->validate(['scheme' => 'required|string|max:100', 'version' => 'required|string|max:100',
            'catalog_review_token' => 'required|uuid', 'change_reason' => 'required|string|min:5|max:1000']);
        $paths = $this->locator->findBundle($data['scheme'], $data['version']);
        app(CatalogReview::class)->consume($request, $paths);
        try {
            $results = $this->coordinator->execute($paths, $data['scheme'], $data['version'], auth()->id());

            return view('gov-classification::manager.import', ['step' => 3, 'results' => $results, 'scheme' => $data['scheme'], 'version' => $data['version']]);
        } catch (Throwable $e) {
            return $this->safeFailure($e);
        }
    }

    private function safeFailure(Throwable $exception)
    {
        $reference = (string) Str::uuid();
        Log::error('Catalog import failed', ['reference_id' => $reference, 'exception' => $exception]);

        return redirect()->route('gov.catalog.import')->with('error', __('tenantops::access.failed', ['reference' => $reference]));
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
