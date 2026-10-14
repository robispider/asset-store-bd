<?php

namespace GovStore\Organization\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use GovStore\Organization\Services\MinistryDirectoryImporter;
use GovStore\Organization\Models\MinistryDirectory;
use Exception;

class MinistryDirectoryController extends Controller
{
    /**
     * Display the Government Directory Importer console
     */
    public function index()
    {
        $user = auth()->user();
        if (!$user->isSuperUser()) {
            abort(403, __('organization_labels::orglabel.directory_controller_unauthorized'));
        }

        $totalRecords = MinistryDirectory::count();
        $latestRecords = MinistryDirectory::with('company')->orderBy('id', 'asc')->limit(10)->get();

        return view('govorg::directory.index', compact('totalRecords', 'latestRecords'));
    }

    /**
     * Execute the importer safely
     */
    public function import(Request $request, MinistryDirectoryImporter $importer)
    {
        $user = auth()->user();
        if (!$user->isSuperUser()) {
            abort(403, __('organization_labels::orglabel.directory_controller_import_unauthorized'));
        }

        try {
            $csvPath = __DIR__ . '/../../database/data/bangladesh_ministries_bilingual.csv';

            abort_if($request->hasFile('csv_file'), 422);

            $result = $importer->import($csvPath);

            // Fixed: changed 'records_synced' to 'total_processed' to align with the importer statistics output
            $msg = __('organization_labels::office.directory_done', [
                'processed' => $result['stats']['total_processed'],
                'created' => $result['stats']['created_companies'],
                'matched' => $result['stats']['matched_companies'],
            ]);
            if (!empty($result['warnings'])) {
                $msg .= ' '.trans_choice('organization_labels::office.directory_warnings', count($result['warnings']), ['count' => count($result['warnings'])]);
            }

            return redirect()->back()->with('success', $msg);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
