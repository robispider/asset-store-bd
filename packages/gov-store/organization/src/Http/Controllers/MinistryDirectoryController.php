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
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            abort(403, 'Unauthorized access to the Government Directory Configurator.');
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
        if (!$user->isSuperUser() && !$user->hasAccess('admin')) {
            abort(403, 'Unauthorized.');
        }

        try {
            $csvPath = __DIR__ . '/../../database/data/bangladesh_ministries_bilingual.csv';

            // If a custom file is uploaded, use its path instead
            if ($request->hasFile('csv_file')) {
                $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);
                $csvPath = $request->file('csv_file')->getRealPath();
            }

            $result = $importer->import($csvPath);

            // Fixed: changed 'records_synced' to 'total_processed' to align with the importer statistics output
            $msg = "Government Directory Synchronized! Records Synced: {$result['stats']['total_processed']}, Companies Created: {$result['stats']['created_companies']}, Companies Matched: {$result['stats']['matched_companies']}.";
            
            if (!empty($result['warnings'])) {
                $msg .= " (Warnings generated: " . count($result['warnings']) . ")";
            }

            return redirect()->back()->with('success', $msg);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }
}
