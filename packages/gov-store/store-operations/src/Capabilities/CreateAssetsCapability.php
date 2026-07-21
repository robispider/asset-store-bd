<?php

namespace GovStore\StoreOperations\Capabilities;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use Illuminate\Support\Facades\DB;
use Exception;

class CreateAssetsCapability implements CapabilityInterface
{
    public function getRequirements(array $config = []): array { return []; }
    public function validate(array $data, array $config = []): array { return []; }

    /**
     * Materializes virtual document lines into actual physical Snipe-IT Assets.
     */
    public function execute(object $item, array $config = []): void
    {
        $document = $item->document;

        // Group EAV metadata entries by row_index to map serials and tags
        $metadata = $item->metadata()->get()->groupBy('row_index');

        for ($r = 0; r < $item->quantity; $r++) {
            $rowMeta = $metadata->get($r);

            if (!$rowMeta) {
                throw new Exception("Missing serialization metadata for item line [Row index: {$r}].");
            }

            $serial = $rowMeta->where('field_key', 'serial_number')->first()?->value;
            $tag = $rowMeta->where('field_key', 'asset_tag')->first()?->value ?? 'TAG-' . uniqid();

            if (!$serial) {
                throw new Exception("Asset serial number is required but missing at index [{$r}].");
            }

            // 1. Programmatically instantiate the core Snipe-IT Asset
            $assetId = DB::table('assets')->insertGetId([
                'model_id'    => $item->product_id,
                'serial'      => $serial,
                'asset_tag'   => $tag,
                'status_id'   => 1, // Snipe-IT Status: "Ready to Deploy"
                'company_id'  => $document->company_id,
                'location_id' => $document->location_id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // 2. Link physical asset instance back to our GRN via polymorphic bridge
            DB::table('gov_asset_registrations')->insert([
                'intake_item_id' => $item->id, // Maps to the unique DocumentItem UUID
                'asset_id'       => $assetId,
                'asset_tag'      => $tag,
                'serial_number'  => $serial,
                'created_at'     => now(),
            ]);

            // 3. Write core Snipe-IT Actionlog (The system audit trace)
            DB::table('action_logs')->insert([
                'item_type'  => \App\Models\Asset::class,
                'item_id'    => $assetId,
                'action_type'=> 'checkout',
                'target_type'=> \App\Models\Location::class,
                'target_id'  => $document->location_id,
                'user_id'    => auth()->id() ?? 1,
                'note'       => "Received under dynamic GRN: {$document->document_number}",
                'created_at' => now(),
            ]);
        }
    }
}