<?php

namespace GovStore\StoreOperations\Adapters;

use App\Models\{Asset, AssetModel};
use GovStore\StoreOperations\Contracts\StockableInterface;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AssetModelAdapter implements StockableInterface
{
    public function __construct(private int $id) {}

    public function getCurrentQuantity(): int
    {
        $officeId = app(TenantContext::class)->locationId;
        if (! $officeId) {
            return 0;
        }

        return Asset::where('model_id', $this->id)->where('location_id', $officeId)->whereNull('assigned_to')
            ->whereHas('status', fn ($query) => $query->where('deployable', 1)->where('archived', 0))->count();
    }

    public function incrementQuantity(int $qty): void
    {
        throw new RuntimeException('Serialized asset quantities are materialized by creating assets.');
    }

    public function decrementQuantity(int $qty): void
    {
        throw new RuntimeException('Serialized asset quantities require selecting individual assets.');
    }

    public function getDisplayName(): string
    {
        return (string) AssetModel::findOrFail($this->id)->name;
    }
}
