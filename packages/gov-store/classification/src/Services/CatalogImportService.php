<?php

namespace GovStore\Classification\Services;

use Illuminate\Support\Facades\DB;
use Exception;

class CatalogImportService
{
     /**
     * STEP 1: OPTIONAL ANALYSIS (Diff Report)
     * Streams the official CSV to compare code presence against the DB.
     */
    public function analyzeDiff(string $metaPath, string $scheme): array
    {
        $csvCodes = [];
        
        if (($handle = fopen($metaPath, 'r')) !== false) {
            $headers = fgetcsv($handle);
            $idx = array_flip(array_map('trim', $headers));
            
            $codeCols = ['Segment', 'Family', 'Class', 'Commodity'];
            
            while (($row = fgetcsv($handle, 4000, ",")) !== false) {
                foreach ($codeCols as $col) {
                    if (isset($idx[$col]) && !empty(trim($row[$idx[$col]]))) {
                        $csvCodes[trim($row[$idx[$col]])] = true;
                    }
                }
            }
            fclose($handle);
        }

        $csvCodeArray = array_keys($csvCodes);
        $dbCodes = DB::table('gov_catalog_nodes')->where('scheme', $scheme)->pluck('code')->toArray();

        return [
            'total_csv'  => count($csvCodeArray),
            'additional' => count(array_diff($csvCodeArray, $dbCodes)),
            'matched'    => count(array_intersect($csvCodeArray, $dbCodes)),
            'missing'    => count(array_diff($dbCodes, $csvCodeArray)),
            'errors'     => [] 
        ];
    }

    /**
     * STEP 2: EXECUTION
     * Streams the Official Metadata CSV to build Nodes, Definitions, and Synonyms synchronously.
     */
    public function execute(string $metaPath, ?string $treePath, string $scheme, string $version, int $userId): array
    {
        $startTime = microtime(true);
        DB::beginTransaction();

        try {
            // Delete old official English synonyms (prevents endless duplication during upgrades)
            DB::table('gov_catalog_synonyms')->where('language', 'en')->delete();

            $stats = $this->streamOfficialDataset($metaPath, $scheme, $version);
            
            $duration = microtime(true) - $startTime;
            
            DB::table('gov_catalog_import_history')->insert([
                'scheme'           => $scheme,
                'version'          => $version,
                'filename'         => basename($metaPath),
                'rows_processed'   => $stats['nodes'],
                'warnings'         => 0,
                'duration_seconds' => $duration,
                'user_id'          => $userId,
                'imported_at'      => now()
            ]);

            DB::commit();

            return [
                'nodes' => $stats['nodes'],
                'meta'  => $stats['defs'],
                'time'  => round($duration, 2)
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Constant-Memory Streaming Parser
     */
    protected function streamOfficialDataset(string $csvPath, string $scheme, string $version): array
    {
        $handle = fopen($csvPath, 'r');
        $headers = fgetcsv($handle);
        $idx = array_flip(array_map('trim', $headers));

        $chunkSize = 1000;
        $nodeBuffer = [];
        $defBuffer = [];
        $synBuffer = [];
        
        $stats = ['nodes' => 0, 'defs' => 0];

        // Keeps track of what has already been buffered in this file to prevent duplication within the same chunk
        $processedCodes = []; 

        $hierarchyMap = [
            ['level' => 1, 'code' => 'Segment',   'title' => 'Segment Title',   'def' => 'Segment Definition'],
            ['level' => 2, 'code' => 'Family',    'title' => 'Family Title',    'def' => 'Family Definition'],
            ['level' => 3, 'code' => 'Class',     'title' => 'Class Title',     'def' => 'Class Definition'],
            ['level' => 4, 'code' => 'Commodity', 'title' => 'Commodity Title', 'def' => 'Commodity Definition']
        ];

        while (($row = fgetcsv($handle, 4000, ",")) !== false) {
            
            $currentHid = '/';
            $parentCode = null;
            $deepestCode = null;

            // Process Horizontal Lineage left-to-right (Segment -> Family -> Class -> Commodity)
            foreach ($hierarchyMap as $h) {
                if (!isset($idx[$h['code']]) || empty(trim($row[$idx[$h['code']]]))) continue;

                $code = trim($row[$idx[$h['code']]]);
                $title = trim($row[$idx[$h['title']]]);
                $def = trim($row[$idx[$h['def']]] ?? '');
                
                // Construct HID directly without recursion
                $currentHid .= $code . '/';
                $deepestCode = $code;

                // 1. Buffer Node (Only if not already processed in this stream)
                if (!isset($processedCodes[$code])) {
                    $nodeBuffer[] = [
                        'scheme'        => $scheme,
                        'version'       => $version,
                        'code'          => $code,
                        'parent_code'   => $parentCode,
                        'level'         => $h['level'],
                        'title_en'      => $title,
                        'hid'           => $currentHid,
                        'is_selectable' => ($h['level'] === 4),
                        'created_at'    => now(),
                        'updated_at'    => now()
                    ];
                    $processedCodes[$code] = true;
                    $stats['nodes']++;

                    // 2. Buffer Definition
                    if ($def) {
                        $defBuffer[] = [
                            'code'          => $code,
                            'definition_en' => $def,
                            'updated_at'    => now()
                        ];
                        $stats['defs']++;
                    }
                }

                $parentCode = $code; // Next node in the loop treats this as parent
            }

            // 3. Buffer Synonyms (Attached to the deepest commodity row)
            if ($deepestCode) {
                if (isset($idx['Synonym']) && $syn = trim($row[$idx['Synonym']])) {
                    $synBuffer[] = ['code' => $deepestCode, 'language' => 'en', 'synonym' => $syn, 'type' => 'common'];
                }
                if (isset($idx['Acronym']) && $acr = trim($row[$idx['Acronym']])) {
                    $synBuffer[] = ['code' => $deepestCode, 'language' => 'en', 'synonym' => $acr, 'type' => 'acronym'];
                }
            }

            // 4. Flush Buffers to Database when chunk size is reached
            if (count($nodeBuffer) >= $chunkSize) {
                $this->flushBuffers($nodeBuffer, $defBuffer, $synBuffer);
            }
        }
        
        fclose($handle);

        // 5. Final flush for remaining rows
        if (count($nodeBuffer) > 0) {
            $this->flushBuffers($nodeBuffer, $defBuffer, $synBuffer);
        }

        return $stats;
    }

    /**
     * Empties arrays into the database, preserving memory.
     */
    protected function flushBuffers(array &$nodes, array &$defs, array &$syns): void
    {
        if (count($nodes) > 0) {
            DB::table('gov_catalog_nodes')->upsert($nodes, ['code'], ['scheme', 'version', 'parent_code', 'level', 'title_en', 'hid', 'is_selectable', 'updated_at']);
        }
        if (count($defs) > 0) {
            DB::table('gov_catalog_definitions')->upsert($defs, ['code'], ['definition_en', 'updated_at']);
        }
        if (count($syns) > 0) {
            // Synonyms don't have unique constraint keys, so we insert directly (old ones were deleted beforehand)
            DB::table('gov_catalog_synonyms')->insert($syns); 
        }

        // Wipe the arrays to free PHP Memory
        $nodes = [];
        $defs = [];
        $syns = [];
    }

    /**
     * Imports the base tree, dynamically computing HIDs and levels based on adjacency keys.
     */
    protected function importTree(string $csvPath, string $scheme, string $version): int
    {
        $nodes = [];
        $keyMap = []; // Maps Internal Key -> Actual Classification Code
        
        $handle = fopen($csvPath, 'r');
        $headers = fgetcsv($handle);
        $idx = array_flip(array_map('trim', $headers));

        // Required headers
        if (!isset($idx['Code'], $idx['Title'], $idx['Key'])) {
            throw new Exception("Tree CSV is missing one of the required headers: 'Key', 'Code', or 'Title'.");
        }

        // Pass 1: Load flat nodes into memory
        while (($row = fgetcsv($handle, 2000, ",")) !== false) {
            if (empty(trim($row[$idx['Code']]))) continue;

            $key = trim($row[$idx['Key']]);
            $nodes[$key] = [
                'code'       => trim($row[$idx['Code']]),
                'parent_key' => isset($idx['Parent key']) ? trim($row[$idx['Parent key']]) : null,
                'title_en'   => trim($row[$idx['Title']]),
                'scheme'     => $scheme,
                'version'    => $version,
            ];
            $keyMap[$key] = $nodes[$key]['code'];
        }
        fclose($handle);

        // Pass 2: Calculate Levels and HIDs recursively using Memoization
        $memoLevel = [];
        $memoHid = [];

        $getLevel = function($key) use (&$getLevel, &$nodes, &$memoLevel) {
            if (empty($key) || !isset($nodes[$key])) return 0;
            if (isset($memoLevel[$key])) return $memoLevel[$key];
            return $memoLevel[$key] = $getLevel($nodes[$key]['parent_key']) + 1;
        };

        $getHid = function($key) use (&$getHid, &$nodes, &$memoHid) {
            if (empty($key) || !isset($nodes[$key])) return '/';
            if (isset($memoHid[$key])) return $memoHid[$key];
            return $memoHid[$key] = $getHid($nodes[$key]['parent_key']) . $nodes[$key]['code'] . '/';
        };

        $dbPayload = [];
        foreach ($nodes as $key => $node) {
            $level = $getLevel($key);
            // Translate the abstract parent_key into the actual parent_code for the DB
            $parentCode = $node['parent_key'] ? ($keyMap[$node['parent_key']] ?? null) : null;
            
            $dbPayload[] = [
                'scheme'        => $scheme,
                'version'       => $version,
                'code'          => $node['code'],
                'parent_code'   => $parentCode,
                'level'         => $level,
                'title_en'      => $node['title_en'],
                'hid'           => $getHid($key),
                'is_selectable' => ($level >= 4), // Lowest inferred levels are selectable commodities
                'created_at'    => now(),
                'updated_at'    => now()
            ];
        }

        // Pass 3: Bulk Upsert in chunks of 1,000
        foreach (array_chunk($dbPayload, 1000) as $chunk) {
            DB::table('gov_catalog_nodes')->upsert(
                $chunk, 
                ['code'], 
                ['scheme', 'version', 'parent_code', 'level', 'title_en', 'hid', 'is_selectable', 'updated_at']
            );
        }

        return count($dbPayload);
    }

    /**
     * Imports Metadata mapping standard hierarchical definitions to their specific codes.
     */
    protected function importMetadata(string $csvPath): int
    {
        $handle = fopen($csvPath, 'r');
        $headers = fgetcsv($handle);
        $idx = array_flip(array_map('trim', $headers));

        $definitions = [];
        $synonyms = [];
        $metaProcessed = 0;

        while (($row = fgetcsv($handle, 4000, ",")) !== false) {
            // Map the four potential levels in a single row
            $levels = [
                'Segment'   => 'Segment Definition',
                'Family'    => 'Family Definition',
                'Class'     => 'Class Definition',
                'Commodity' => 'Commodity Definition'
            ];

            $lowestCode = null;

            foreach ($levels as $codeCol => $defCol) {
                if (!isset($idx[$codeCol]) || !isset($idx[$defCol])) continue;
                
                $code = trim($row[$idx[$codeCol]]);
                $def = trim($row[$idx[$defCol]]);
                
                if ($code) {
                    $lowestCode = $code; // Tracks the deepest node on this specific row
                    if ($def && !isset($definitions[$code])) {
                        $definitions[$code] = [
                            'code'          => $code,
                            'definition_en' => $def,
                            'created_at'    => now(),
                            'updated_at'    => now()
                        ];
                    }
                }
            }

            // Process Synonyms/Acronyms tied to the deepest node of the row
            if ($lowestCode) {
                if (isset($idx['Synonym']) && $syn = trim($row[$idx['Synonym']])) {
                    // Prevent duplicate synonyms on the same code
                    $synKey = $lowestCode . '_' . $syn;
                    $synonyms[$synKey] = ['code' => $lowestCode, 'language' => 'en', 'synonym' => $syn, 'type' => 'common', 'created_at' => now()];
                }
                if (isset($idx['Acronym']) && $acr = trim($row[$idx['Acronym']])) {
                    $acrKey = $lowestCode . '_acr_' . $acr;
                    $synonyms[$acrKey] = ['code' => $lowestCode, 'language' => 'en', 'synonym' => $acr, 'type' => 'acronym', 'created_at' => now()];
                }
            }
            $metaProcessed++;
        }
        fclose($handle);

        // Bulk Upsert Definitions
        foreach (array_chunk(array_values($definitions), 1000) as $chunk) {
            DB::table('gov_catalog_definitions')->upsert($chunk, ['code'], ['definition_en', 'updated_at']);
        }

        // Flush old official reference synonyms (preserves local translations) and Bulk Insert new ones
        DB::table('gov_catalog_synonyms')->where('language', 'en')->whereIn('type', ['common', 'acronym'])->delete();
        foreach (array_chunk(array_values($synonyms), 1000) as $chunk) {
            DB::table('gov_catalog_synonyms')->insert($chunk);
        }

        return $metaProcessed;
    }
}