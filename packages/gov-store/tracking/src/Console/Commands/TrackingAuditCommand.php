<?php

namespace GovStore\Tracking\Console\Commands;

use App\Models\Asset;
use App\Models\Location;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Services\ScopeValidatorService;
use Illuminate\Console\Command;

class TrackingAuditCommand extends Command
{
    protected $signature = 'govstore:tracking-audit';
    protected $description = 'Perform background operational health and compliance audits on linked references and scopes';

    protected ScopeValidatorService $scopeValidator;

    public function __construct(ScopeValidatorService $scopeValidator)
    {
        parent::__construct();
        $this->scopeValidator = $scopeValidator;
    }

    public function handle(): int
    {
        $this->info("=== Starting GovStore Reference Compliance Audit ===");

        $this->auditOrphanedAssociations();
        $this->auditScopeAnomalies();

        $this->info("=== Audit Completed successfully. ===");
        return 0;
    }

    protected function auditOrphanedAssociations(): void
    {
        $this->warn("\nChecking for orphaned associations...");
        
        $orphans = TrackingAssociation::where('associatable_type', Asset::class)
            ->whereNotExists(function ($query) {
                $query->selectRaw(1)
                    ->from('hardware')
                    ->whereColumn('hardware.id', 'gov_tracking_associations.associatable_id');
            })->get();

        if ($orphans->isEmpty()) {
            $this->info("✔ No orphaned asset associations discovered.");
            return;
        }

        foreach ($orphans as $orphan) {
            $this->error("✖ Orphan Detected: Association #{$orphan->id} points to missing Asset ID #{$orphan->associatable_id}.");
            if ($this->confirm("Would you like to purge this orphaned reference?", true)) {
                $orphan->delete();
                $this->info("Purged association.");
            }
        }
    }

    protected function auditScopeAnomalies(): void
    {
        $this->warn("\nChecking for geographical scope boundaries anomalies...");
        $anomaliesCount = 0;

        $activeAssociations = TrackingAssociation::with('reference')
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->get();

        foreach ($activeAssociations as $assoc) {
            $asset = Asset::find($assoc->associatable_id);
            if ($asset && $asset->location_id) {
                // Assert geographical boundaries
                $isApplicable = $this->scopeValidator->validateApplicability($assoc->reference, $asset->location_id);
                
                if (!$isApplicable) {
                    $anomaliesCount++;
                    $locationName = Location::find($asset->location_id)?->name ?? "Location #{$asset->location_id}";
                    $this->error("✖ Compliance Alert: Asset Tag '{$asset->asset_tag}' (Reference: '{$assoc->reference->reference_code}') resides at '{$locationName}' which violates target applicability boundaries.");
                }
            }
        }

        if ($anomaliesCount === 0) {
            $this->info("✔ All active associated assets conform to target geographical applicability boundaries.");
        } else {
            $this->warn("\nIdentified {$anomaliesCount} operational boundaries violations.");
        }
    }
}