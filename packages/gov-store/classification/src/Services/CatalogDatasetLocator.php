<?php

namespace GovStore\Classification\Services;

use Exception;

class CatalogDatasetLocator
{
    /**
     * Resolves the verified absolute paths for the pre-compiled catalog files.
     * Aligns perfectly with your physical directory structure.
     */
    public function findBundle(string $scheme, string $version): array
    {
        // Resolves from src/Services -> up to src -> into database/data
        $baseDir = realpath(__DIR__ . '/../database/data');

        if (!$baseDir || !is_dir($baseDir)) {
            throw new Exception(__('classification::texts.svc_directory_not_resolved'));
        }

        $paths = [
            'nodes' => $baseDir . '/compiled_nodes.csv',
            'defs'  => $baseDir . '/compiled_definitions.csv',
            'syns'  => $baseDir . '/compiled_synonyms.csv',
        ];

        // Ensure all three essential compiled files exist on disk
        foreach ($paths as $key => $path) {
            if (!file_exists($path)) {
                throw new Exception("Required pre-compiled catalog file missing: " . basename($path) . " (Expected at: {$path})");
            }
        }

        return $paths;
    }
}