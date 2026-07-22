<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Exception;

class CreateAssetsCapability implements CapabilityInterface
{
    public function getRequirements(array $config = []): array { return []; }
    public function validate(array $data, array $config = []): array { return []; }

    /**
     * Materializes virtual document lines into physical Snipe-IT Assets.
     * Uses an ultra-safe foreach loop to prevent any possible increment typos.
     */
    public function execute(object $item, array $config = []): void
    {
        $document = $item->document;
        $quantity = (int) $item->quantity;

        if ($quantity <= 0) {
            return;
        }

        // Group EAV metadata entries by row_index
        $metadata = $item->metadata()->get()->groupBy('row_index');

        // Loop using foreach over range() to completely avoid any $r++ syntax
        foreach (range(0, $quantity - 1) as $r) {
            $rowMeta = $metadata->get($r);

            $serial = null;
            $tag = null;

            if ($rowMeta) {
                $serial = $rowMeta->where('field_key', 'serial_number')->first()?->value;
                $tag = $rowMeta->where('field_key', 'asset_tag')->first()?->value;
            }

            // Fallback: If category did not require serials, auto-generate a structured, traceable serial
            if (!$serial) {
                $serial = 'SN-AUTO-' . $document->getDocumentNumber() . '-' . $item->product_id . '-' . $r;
            }

            // Fallback: If category did not require tags, auto-generate a traceable tag
            if (!$tag) {
                $tag = 'TAG-AUTO-' . $document->getDocumentNumber() . '-' . uniqid();
            }

            // 1. Programmatically instantiate using Snipe-IT's native Eloquent Model
            $asset = new Asset();
            $asset->model_id    = $item->product_id;
            $asset->serial      = $serial;
            $asset->asset_tag   = $tag;
            $asset->status_id   = $config['status_id'] ?? 1; // Defaults to "Ready to Deploy"
            $asset->company_id  = $document->company_id;
            $asset->location_id = $document->location_id;
            
            if (!$asset->save()) {
                throw new Exception("Failed to instantiate native Snipe-IT asset for serial [{$serial}].");
            }

            // 2. Link physical asset instance back to our GRN via polymorphic bridge
            DB::table('gov_asset_registrations')->insert([
                'intake_item_id' => $item->id, // Maps to the unique DocumentItem UUID
                'asset_id'       => $asset->id,
                'asset_tag'      => $asset->asset_tag,
                'serial_number'  => $asset->serial,
                'created_at'     => now(),
            ]);

            // 3. Trigger Snipe-IT's native checkout/action logger
            $asset->logCheckout("Received under dynamic GRN: {$document->document_number}", auth()->user() ?? app(\App\Models\User::class)->first());
        }
    }

    public function renderUI(object $item = null, array $config = []): string
    {
        return ''; // Execution plugins require no UI
    }
}