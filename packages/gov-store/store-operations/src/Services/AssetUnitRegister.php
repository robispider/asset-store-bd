<?php

namespace GovStore\StoreOperations\Services;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Location;
use App\Models\User;
use GovStore\StoreOperations\Models\InventoryMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only view of serialised stock: the asset-model ledger balance per office and the
 * individual units that store receipts created (gov_asset_registrations → assets).
 */
class AssetUnitRegister
{
    public const MORPH_KEYS = ['assetmodel', 'asset_model', AssetModel::class];

    /** Ledger balance in units per asset model for one office, keyed by model id. */
    public function balances(int $locationId): Collection
    {
        return InventoryMovement::withoutGlobalScopes()
            ->whereIn('stockable_type', self::MORPH_KEYS)
            ->where('location_id', $locationId)
            ->groupBy('stockable_id')
            ->selectRaw("stockable_id, COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE -quantity END), 0) AS balance")
            ->pluck('balance', 'stockable_id')
            ->map(fn ($balance) => (int) $balance);
    }

    /** Units in, out and on hand for one asset model in one office, over the whole ledger. */
    public function totals(int $modelId, int $locationId): array
    {
        $row = InventoryMovement::withoutGlobalScopes()
            ->whereIn('stockable_type', self::MORPH_KEYS)
            ->where('stockable_id', $modelId)
            ->where('location_id', $locationId)
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE 0 END), 0) AS units_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'OUT' THEN quantity ELSE 0 END), 0) AS units_out")
            ->first();
        [$in, $out] = [(int) ($row->units_in ?? 0), (int) ($row->units_out ?? 0)];

        return ['in' => $in, 'out' => $out, 'balance' => $in - $out];
    }

    /**
     * Units received into the office for one asset model, oldest receipt first.
     * Each row is plain data for the asset card; `ready` marks units free to issue.
     */
    public function units(int $modelId, int $locationId): Collection
    {
        $registrations = DB::table('gov_asset_registrations as r')
            ->join('gov_document_items as i', 'i.id', '=', 'r.intake_item_id')
            ->join('gov_documents as d', 'd.id', '=', 'i.document_id')
            ->whereIn('i.product_type', self::MORPH_KEYS)
            ->where('i.product_id', $modelId)
            ->where('d.location_id', $locationId)
            ->orderBy('r.created_at')->orderBy('r.asset_tag')
            ->get(['r.asset_id', 'r.asset_tag', 'r.serial_number', 'r.created_at', 'd.id as document_id',
                'd.document_number', 'd.type as document_type', 'd.posted_at']);

        $assets = Asset::withoutGlobalScopes()->with('status')
            ->whereIn('id', $registrations->pluck('asset_id')->unique())->get()->keyBy('id');
        $holders = $this->holders($assets);

        return $registrations->map(function ($registration) use ($assets, $holders, $locationId) {
            $asset = $assets->get($registration->asset_id);
            $holder = $asset?->assigned_to ? $holders->get($asset->assigned_type)?->get($asset->assigned_to) : null;
            $statusKey = $this->statusKey($asset);

            return [
                'asset_id' => $asset?->id,
                'tag' => $asset->asset_tag ?? $registration->asset_tag,
                'serial' => $asset->serial ?? $registration->serial_number,
                'received_at' => $registration->posted_at ?? $registration->created_at,
                'document_id' => $registration->document_id,
                'document_type' => $registration->document_type,
                'document_number' => $registration->document_number,
                'status_key' => $statusKey,
                'removed' => ! $asset || (bool) $asset->deleted_at,
                // Deployed and deleted units use the kit's own label; others show the native status name.
                'status_label' => $asset && ! $asset->deleted_at && ! $asset->assigned_to ? ($asset->status->name ?? null) : null,
                'holder' => $holder ? $this->holderName($holder) : null,
                'warranty_until' => $asset?->warranty_expires,
                'warranty_months' => $asset?->warranty_months,
                'ready' => $statusKey === 'deployable' && (int) $asset->location_id === $locationId,
            ];
        });
    }

    /**
     * Current holders grouped by type. Asset::assignedTo() is morphTo('assigned', …), so eager
     * loading it by its method name never attaches the result; load each holder type once instead.
     */
    private function holders(Collection $assets): Collection
    {
        return $assets->filter(fn (Asset $asset) => $asset->assigned_to
                && in_array($asset->assigned_type, [User::class, Location::class, Asset::class], true))
            ->groupBy('assigned_type')
            ->map(fn (Collection $group, string $type) => $type::withoutGlobalScopes()
                ->whereIn('id', $group->pluck('assigned_to')->unique())->get()->keyBy('id'));
    }

    private function statusKey(?Asset $asset): string
    {
        if (! $asset || $asset->deleted_at) {
            return 'archived';
        }
        if ($asset->assigned_to) {
            return 'deployed';
        }

        return $asset->status?->getStatuslabelType() ?? 'pending';
    }

    private function holderName(object $holder): string
    {
        return match (true) {
            $holder instanceof User => (string) $holder->display_name,
            $holder instanceof Location => (string) $holder->name,
            $holder instanceof Asset => (string) $holder->asset_tag,
            default => (string) ($holder->name ?? ''),
        };
    }
}
