<?php

namespace GovStore\StoreOperations\Services;

use App\Models\AssetModel;
use App\Models\CustomFieldset;
use Illuminate\Support\Facades\Log;

class ModelFieldsetAssignmentService
{
    /**
     * Administrative Task: Associates the global fieldset to all models.
     * Never runs inside storekeeper transactional flows.
     */
    public function assignGlobalFieldsetToAllModels(): void
    {
        $defaultFieldset = CustomFieldset::where('name', CustomFieldProvisioner::DEFAULT_FIELDSET_NAME)->first();

        if (!$defaultFieldset) {
            throw new \Exception("GovStore Global Baseline fieldset has not been provisioned yet. Run 'govstore:sync-fields' first.");
        }

        // Fetch all asset models that do not have any fieldset mapped
        $modelsToUpdate = AssetModel::whereNull('fieldset_id')->get();

        foreach ($modelsToUpdate as $model) {
            $model->fieldset_id = $defaultFieldset->id;
            $model->save();
            
            Log::info("GovStore Admin: Associated global fieldset with Model [{$model->name}]");
        }
    }
}