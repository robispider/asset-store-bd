<?php

namespace GovStore\Metadata\Console\Commands;

use Illuminate\Console\Command;
use GovStore\Metadata\Services\MetadataHealthService;

class HealthMetadataCommand extends Command
{
    protected $signature = 'govstore:metadata-health';
    protected $description = 'Displays diagnostic checks on the GovStore Metadata Platform';

    public function handle(MetadataHealthService $service): int
    {
        $this->info('=========================================');
        $this->info('  GovStore Metadata Platform Health Check  ');
        $this->info('=========================================');

        $report = $service->generateReport();

        $this->comment("\nMetadata Providers Loaded:");
        foreach ($report->providers as $p) {
            $this->line("  ✓ {$p['name']} [Version: {$p['version']}] ({$p['fields_count']} fields)");
        }

        $this->comment("\nMetadata Compliance Assessment:");
        $this->line("  Total Models Assessed : {$report->totalModels}");
        $this->line("  Compliant Models      : {$report->compliantModels}");
        $this->line("  Requires Convergence  : {$report->nonCompliantModels}");

        if (!empty($report->nonCompliantModelDetails)) {
            $this->warn("\nNon-Compliant Models Found:");
            foreach ($report->nonCompliantModelDetails as $m) {
                $this->line("  - [ID: {$m['id']}] {$m['name']}");
            }
        }

        $this->comment("\nMapping Integrity Verification:");
        if (empty($report->orphanMappings)) {
            $this->info("  ✓ All active physical mappings are valid.");
        } else {
            $this->error("  ⚠️ Orphan physical mappings detected:");
            foreach ($report->orphanMappings as $o) {
                $this->line("  - Identifier: {$o['identifier']} (Custom Field ID: {$o['custom_field_id']})");
                $this->line("    Reason: {$o['reason']}");
            }
        }

        $this->info("\n=========================================");
        if ($report->healthScore === 100) {
            $this->info("  SYSTEM STATUS: HEALTHY [Score: {$report->healthScore}%]");
        } elseif ($report->healthScore >= 80) {
            $this->warn("  SYSTEM STATUS: WARNING [Score: {$report->healthScore}%]");
        } else {
            $this->error("  SYSTEM STATUS: CRITICAL [Score: {$report->healthScore}%]");
        }
        $this->info("=========================================");

        return 0;
    }
}