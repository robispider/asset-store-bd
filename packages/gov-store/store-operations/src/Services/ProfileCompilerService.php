<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\Profile;
use GovStore\StoreOperations\Models\Capability;
use Illuminate\Support\Facades\DB;
use Exception;

class ProfileCompilerService
{
    /**
     * Compile the complete operational snapshot for a Document based on its current line items.
     * This is executed during the DRAFT phase.
     */
    public function compileDocument(Document $document): array
    {
        $itemsPayload = [];

        foreach ($document->items as $item) {
            $compiled = $this->compileItem($item->product_type, $item->product_id);
            
            $itemsPayload[] = [
                'product_id'   => $item->product_id,
                'product_type' => $item->product_type,
                'capabilities' => $compiled['capabilities'],
                'requirements' => $compiled['requirements'],
            ];
        }

        return [
            'document_id' => $document->id,
            'items'       => $itemsPayload
        ];
    }

    /**
     * Recursively compiles the profile for a single item.
     * Walks up the parent chain: Model Override -> Category -> Major Type -> Global.
     */
    public function compileItem(string $productType, int $productId): array
    {
        // 1. Resolve the starting Profile ID in the recursive chain
        $startingProfileId = $this->resolveStartingProfileId($productType, $productId);

        // 2. Walk up the parent tree (from leaf to root)
        $profileChain = [];
        $currentProfile = Profile::with('capabilities.requirements')->find($startingProfileId);

        while ($currentProfile) {
            // Push to beginning of array so Global (root) sits at index 0, followed by Major Type, etc.
            array_unshift($profileChain, $currentProfile);
            $currentProfile = $currentProfile->parent_id 
                ? Profile::with('capabilities.requirements')->find($currentProfile->parent_id) 
                : null;
        }

        // 3. Merge capabilities and requirements Top-Down (Global -> Major Type -> Category -> Override)
        $mergedCapabilities = [];
        $mergedRequirements = [];

        foreach ($profileChain as $profile) {
            foreach ($profile->capabilities as $capability) {
                $code = $capability->code;

                // Merge capability configuration (child redefinitions override parents)
                $config = json_decode($capability->pivot->config_payload ?? '{}', true) ?? [];
                $mergedCapabilities[$code] = [
                    'code'   => $code,
                    'type'   => $capability->type,
                    'config' => $config
                ];

                // Gather requirements exposed by this capability
                foreach ($capability->requirements as $req) {
                    $mergedRequirements[$req->field_key] = [
                        'key'   => $req->field_key,
                        'type'  => $req->field_type,
                        'rules' => $req->validation_rules
                    ];
                }
            }
        }

        return [
            'capabilities' => array_values($mergedCapabilities),
            'requirements' => array_values($mergedRequirements)
        ];
    }

    /**
     * Core resolution mapping logic. Determines which Profile ID 
     * a Snipe-IT product model, consumable, or accessory starts with.
     */
    protected function resolveStartingProfileId(string $productType, int $productId): int
    {
        // Fallback IDs based on the Phase 1 Database Seeder
        $globalBaseId   = 1;
        $assetMajorId   = 2;
        $notebookCatId  = 3;

        if ($productType === 'consumable') {
            // Consumables inherit directly from Global Base (simple quantity entry)
            return $globalBaseId;
        }

        if ($productType === 'asset_model') {
            // Resolve the core Snipe-IT Model's category
            $model = DB::table('models')->where('id', $productId)->first();
            
            if (!$model) {
                throw new Exception("Core Snipe-IT Asset Model [ID: {$productId}] does not exist.");
            }

            // Notebook mapping rule (Example category mapping)
            // If the model's category matches Notebook, return NotebookCategoryProfile.
            // In a production build, a mapping table maps category_id -> profile_id.
            $categoryName = DB::table('categories')->where('id', $model->category_id)->value('name');
            if (strtolower($categoryName) === 'notebook' || strtolower($categoryName) === 'laptops') {
                return $notebookCatId;
            }

            // Fallback to Asset Major Type
            return $assetMajorId;
        }

        // Fallback to root Global Base for Accessories/Components in Phase 1
        return $globalBaseId;
    }
}