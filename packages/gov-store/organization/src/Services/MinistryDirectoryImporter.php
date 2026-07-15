<?php

namespace GovStore\Organization\Services;

use GovStore\Organization\Models\MinistryDirectory;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Exception;

class MinistryDirectoryImporter
{
    /**
     * Executes the safe, multi-layered synchronization pipeline with bilingual name formatting.
     */
    public function import(string $csvPath): array
    {
        if (!file_exists($csvPath)) {
            throw new Exception("The specified CSV dataset was not found at path: {$csvPath}");
        }

        $rawRows = [];
        $lookupMap = [];
        $warnings = [];

        // --- PASS 1: Read and Parse CSV into Memory ---
        if (($handle = fopen($csvPath, 'r')) !== false) {
            fgetcsv($handle, 1000, ","); // Skip header

            while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                if (empty($row) || count($row) < 7) continue;

                $id = (int)$row[0];
                if ($id <= 0) continue;

                $enName = trim($row[2]);

                $rawRows[$id] = [
                    'id' => $id,
                    'bn_name' => trim($row[1]),
                    'en_name' => $enName,
                    'org_type' => trim($row[3]),
                    'parent_name' => trim($row[4]),
                    'parent_bn_name' => trim($row[5]), // Bangla Parent Name
                    'domain' => empty(trim($row[6])) ? null : trim($row[6]),
                ];

                $lookupMap[$enName] = $id;
            }
            fclose($handle);
        }

        // --- PASS 2: Parent Resolution & Circularity Guards ---
        foreach ($rawRows as $id => &$row) {
            $parentId = null;
            if (!empty($row['parent_name'])) {
                if (isset($lookupMap[$row['parent_name']])) {
                    $parentId = $lookupMap[$row['parent_name']];
                    if ($parentId === $id) {
                        $warnings[] = "Circular relationship ignored on ID {$id} ({$row['en_name']}).";
                        $parentId = null;
                    }
                } else {
                    $warnings[] = "Unresolved parent reference '{$row['parent_name']}' on ID {$id}.";
                }
            }
            $row['parent_id'] = $parentId;
        }

        // --- PASS 3: Recursive HID Tree Builder ---
        foreach ($rawRows as $id => &$row) {
            $row['hid'] = $this->buildHidPath($id, $rawRows);
        }

        $stats = [
            'total_processed'   => 0,
            'created_companies' => 0,
            'updated_companies' => 0,
            'matched_companies' => 0,
        ];

        // --- PASS 4: DB Transaction with Fallback Synchronization & Renaming Logic ---
        DB::transaction(function () use ($rawRows, &$stats) {
            foreach ($rawRows as $id => $row) {
                
                // 1. Generate Target Bilingual Name Format
                $parentBnName = trim($row['parent_bn_name']);
                if (!empty($parentBnName) && $parentBnName !== '?') {
                    // Format with Parent: Bangla Name / English Name, Parent Bangla Name
                    $targetName = $row['bn_name'] . ' / ' . $row['en_name'] . ', ' . $parentBnName;
                } else {
                    // Format without Parent: Bangla Name / English Name
                    $targetName = $row['bn_name'] . ' / ' . $row['en_name'];
                }

                $company = null;
                $directory = MinistryDirectory::find($id);

                // Priority 1: Use already established company_id linkage
                if ($directory && $directory->company_id) {
                    $company = Company::find($directory->company_id);
                }

                // Priority 2: Match by unique government domain link
                if (!$company && $row['domain']) {
                    $matchedDir = MinistryDirectory::where('domain', $row['domain'])
                        ->whereNotNull('company_id')
                        ->first();
                        
                    if ($matchedDir) {
                        $company = Company::find($matchedDir->company_id);
                    }
                }

                // Priority 3: Match by Target Bilingual Name
                if (!$company) {
                    $company = Company::where('name', $targetName)->first();
                }

                // Priority 4: Fallback Match by raw English Name (for migrating legacy records)
                if (!$company) {
                    $company = Company::where('name', $row['en_name'])->first();
                }

                // Priority 5: Synchronize and Rename, or Create fresh
                if ($company) {
                    // Automatically renames legacy or mismatched Company records to the new format
                    if ($company->name !== $targetName) {
                        $company->update(['name' => $targetName]);
                        $stats['updated_companies']++;
                    } else {
                        $stats['matched_companies']++;
                    }
                } else {
                    // Completely missing: Provision a fresh Snipe-IT Company catalog entry using target format
                    $company = Company::create([
                        'name' => $targetName,
                    ]);
                    $stats['created_companies']++;
                }

                // Save or Update Directory Entry
                MinistryDirectory::updateOrCreate(
                    ['id' => $id],
                    [
                        'bn_name' => $row['bn_name'],
                        'en_name' => $row['en_name'],
                        'org_type' => $row['org_type'],
                        'parent_id' => $row['parent_id'],
                        'hid' => $row['hid'],
                        'domain' => $row['domain'],
                        'company_id' => $company->id,
                    ]
                );

                $stats['total_processed']++;
            }
        });

        return [
            'stats' => $stats,
            'warnings' => $warnings,
            'success' => true
        ];
    }

    protected function buildHidPath(int $id, array $rawRows, array $visited = []): string
    {
        if (in_array($id, $visited)) return '/';
        $row = $rawRows[$id];
        $visited[] = $id;

        if (!$row['parent_id']) {
            return '/' . $id . '/';
        }

        return $this->buildHidPath($row['parent_id'], $rawRows, $visited) . $id . '/';
    }
}
