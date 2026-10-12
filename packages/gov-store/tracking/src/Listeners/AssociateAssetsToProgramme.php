<?php

namespace GovStore\Tracking\Listeners;

use App\Models\Asset;
use GovStore\Tracking\Events\AssetsReceivedViaGRN;
use GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme;
use Illuminate\Support\Facades\DB;

/** Compatibility adapter; uses the same durable delivery path as modern receipts. */
class AssociateAssetsToProgramme
{
    public function handle(AssetsReceivedViaGRN $event): void
    {
        DB::transaction(function () use ($event) {
            $assets = Asset::with('model')->whereIn('id', array_unique($event->assetIds))->get();
            if ($assets->count() !== count(array_unique($event->assetIds))) {
                throw new \InvalidArgumentException('Programme receipt contains inaccessible assets.');
            }
            foreach ($assets->groupBy(fn ($asset) => $asset->location_id.':'.$asset->model_id.':'.$asset->supplier_id) as $group) {
                $asset = $group->first();
                app(AssociateInventoryToProgramme::class)->handle(new InventoryMaterializedAgainstProgramme(
                    $event->trackingCodeString, (int) $asset->model->category_id, (int) $asset->model_id,
                    (int) $asset->model->manufacturer_id, (int) $asset->location_id, $group->count(),
                    (float) $group->sum('purchase_cost'), (int) $asset->supplier_id, $event->actorId,
                    $event->grnReferenceNumber, $group->map(fn ($a) => ['type' => Asset::class, 'id' => $a->id])->all(),
                    $event->overrideReason
                ));
            }
        });
    }
}
