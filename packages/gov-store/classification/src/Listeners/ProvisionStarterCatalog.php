<?php

namespace GovStore\Classification\Listeners;

use GovStore\Classification\Models\CatalogCollectionNode;
use GovStore\Classification\Jobs\ExecuteStarterTemplateJob;
use Illuminate\Support\Facades\Log;

class ProvisionStarterCatalog
{
    /**
     * Handle the event.
     * Assuming the event provides $event->location (App\Models\Location) and $event->userId
     */
    public function handle($event)
    {
        try {
            $location = $event->location;
            
            $officeType = $event->officeType ?? 'default';

            if (!$location->company_id || !$event->catalogActorId) {
                Log::info('Starter catalog waits for a company-scoped office administrator.');
                return;
            }
            
            // 1. Get the collection names from our config map
            $collectionNames = config("starter_templates.office_types.{$officeType}");

            if (!$collectionNames) {
                Log::info("No starter template defined for office type: {$officeType}");
                return;
            }

            // 2. Fetch all unique UNSPSC codes belonging to these collections
            $codes = CatalogCollectionNode::whereHas('collection', function($query) use ($collectionNames) {
                $query->whereIn('name', $collectionNames)
                      ->where('is_active', true);
            })->pluck('code')->unique()->toArray();

            if (empty($codes)) {
                Log::warning("Starter template found for {$officeType}, but collections are empty.");
                return;
            }

            if (!filter_var($event->catalogActorId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
                Log::warning('Starter catalog requires an explicit office administrator actor.');
                return;
            }

            // 3. Dispatch the background job to provision the catalog for this specific location
            ExecuteStarterTemplateJob::dispatch(
                $codes, 
                'location', 
                $location->id, 
                (int) $event->catalogActorId
            );

            Log::info("Dispatched Starter Template job for Location ID: {$location->id} with " . count($codes) . " categories.");

        } catch (\Exception $e) {
            Log::error("Failed to apply Starter Template: " . $e->getMessage());
        }
    }
}
