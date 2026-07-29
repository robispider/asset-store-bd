<?php

namespace GovStore\StoreOperations\Console\Commands;

use Illuminate\Console\Command;
use GovStore\StoreOperations\Services\CustomFieldProvisioner;
use GovStore\StoreOperations\Services\ModelFieldsetAssignmentService;

class SyncGovStoreFields extends Command
{
    protected $signature = 'govstore:sync-fields';
    protected $description = 'Provisions custom fields and maps them to asset models administratively';

    public function handle(
        CustomFieldProvisioner $provisioner,
        ModelFieldsetAssignmentService $assignmentService
    ) {
        $this->info('Initializing GovStore Custom Field Seeding...');

        try {
            // 1. Setup the Fields and the Fieldset globally
            $provisioner->ensureSystemSetup();
            $this->info('✅ Fields & Fieldset successfully provisioned.');

            // 2. Administratively map the global fieldset to models lacking one
            $this->info('Mapping global fieldset to asset models...');
            $assignmentService->assignGlobalFieldsetToAllModels();
            $this->info('✅ Model mappings completed.');

            $allocationCol = $provisioner->getDbColumn(CustomFieldProvisioner::FIELD_ALLOCATION);
            $grnCol        = $provisioner->getDbColumn(CustomFieldProvisioner::FIELD_GRN);

            $this->line("");
            $this->line("1. Ministry Allocation Code -> Column: [{$allocationCol}]");
            $this->line("2. GRN Document No        -> Column: [{$grnCol}]");
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Bootstrap Failed: ' . $e->getMessage());
            return 1;
        }
    }
}