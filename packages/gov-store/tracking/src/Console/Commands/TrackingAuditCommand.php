<?php

namespace GovStore\Tracking\Console\Commands;

use App\Models\Asset;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Services\ScopeValidatorService;
use Illuminate\Console\Command;

class TrackingAuditCommand extends Command
{
    protected $signature = 'govstore:tracking-audit';
    protected $description = 'Scans associated assets to verify compliance with Tracking Code geographical scopes.';

    protected ScopeValidatorService $scopeValidator;

    public function __construct(ScopeValidatorService $scopeValidator)
    {
        parent::__construct();
        $this->scopeValidator = $scopeValidator;
    }

    public function handle(): int
    {
        $this->info("=== GovStore Tracking Compliance Audit ===");
        $this->warn("Scanning active associations for geographical violations...\n");

        $anomaliesCount = 0;

        $activeAssociations = TrackingAssociation::with('trackingCode.scopes')
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->get();

        foreach ($activeAssociations as $assoc) {
            $asset = Asset::find($assoc->associatable_id);
            
            if ($asset && $asset->location_id) {
                // We mock the API evaluation call to check just the geography/location
                $validation = $this->scopeValidator->validateExecutionScope($assoc->trackingCode, $asset->location_id);
                
                if (!$validation['is_valid']) {
                    $anomaliesCount++;
                    $this->error("✖ VIOLATION: Asset Tag '{$asset->asset_tag}' (Code: '{$assoc->trackingCode->tracking_code}')");
                    $this->line("  ↳ Location ID: {$asset->location_id}");
                    $this->line("  ↳ Reason: " . $validation['message'] . "\n");
                }
            }
        }

        if ($anomaliesCount === 0) {
            $this->info("✔ Audit Complete. All tracked assets conform to their geographical boundaries.");
        } else {
            $this->warn("Audit Complete. Identified {$anomaliesCount} compliance violations.");
        }

        return 0;
    }
}