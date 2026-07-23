<?php

namespace GovStore\StoreOperations\Observers;

use App\Models\Category;
use GovStore\StoreOperations\Models\Profile;
use GovStore\StoreOperations\Models\ProfileAssignment;
use Illuminate\Support\Facades\Log;
use Exception;

class SnipeCategoryObserver
{
    /**
     * Listen to the Snipe-IT Category created event.
     * Automatically assigns a global standard policy to newly created categories.
     */
    public function created(Category $category)
    {
        try {
            // Determine which Global Standard policy to apply based on native type
            $policyName = ($category->category_type === 'asset') 
                ? 'System Default Asset Standard' 
                : 'System Default Consumable Standard';

            $policyId = Profile::where('name', $policyName)
                ->where('scope', 'GLOBAL')
                ->where('status', 'PUBLISHED')
                ->value('id');

            if ($policyId) {
                // Auto-adopt the global policy for this new category
                ProfileAssignment::create([
                    'profile_id'     => $policyId,
                    'target_type'    => Category::class,
                    'target_id'      => $category->id,
                    'assigned_by'    => auth()->id() ?? 1,
                    'effective_from' => now(),
                ]);

                Log::info("Gov-Store: Auto-assigned [{$policyName}] policy to new Category: {$category->name}");
            }
        } catch (Exception $e) {
            Log::error("Gov-Store: Failed to auto-assign policy for Category {$category->name}. " . $e->getMessage());
        }
    }
}