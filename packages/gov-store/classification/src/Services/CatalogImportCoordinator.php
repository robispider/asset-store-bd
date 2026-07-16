<?php

namespace GovStore\Classification\Services;

use GovStore\Classification\Services\Importers\NodeImporter;
use GovStore\Classification\Services\Importers\DefinitionImporter;
use GovStore\Classification\Services\Importers\SynonymImporter;
use Illuminate\Support\Facades\DB;
use Exception;

class CatalogImportCoordinator
{
    protected NodeImporter $nodes;
    protected DefinitionImporter $defs;
    protected SynonymImporter $syns;

    public function __construct(NodeImporter $nodes, DefinitionImporter $defs, SynonymImporter $syns)
    {
        $this->nodes = $nodes;
        $this->defs = $defs;
        $this->syns = $syns;
    }

    public function execute(array $paths, string $scheme, string $version, int $userId): array
    {
        $startTime = microtime(true);
        DB::beginTransaction();

        try {
            // 1. Ingest Nodes
            $nodeCount = $this->nodes->import($paths['nodes'], $scheme, $version);

            // 2. Ingest Definitions
            $defCount = $this->defs->import($paths['defs']);

            // 3. Ingest Synonyms
            $synCount = $this->syns->import($paths['syns']);

            $duration = microtime(true) - $startTime;

            // 4. Log Audit Trail
            DB::table('gov_catalog_import_history')->insert([
                'scheme'           => $scheme,
                'version'          => $version,
                'filename'         => basename($paths['nodes']),
                'rows_processed'   => $nodeCount,
                'warnings'         => 0,
                'duration_seconds' => $duration,
                'user_id'          => $userId,
                'imported_at'      => now()
            ]);

            DB::commit();

            return [
                'nodes' => $nodeCount,
                'defs'  => $defCount,
                'syns'  => $synCount,
                'time'  => round($duration, 2)
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}