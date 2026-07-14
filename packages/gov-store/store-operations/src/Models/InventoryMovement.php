<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use GovStore\TenantScope\Scopes\MinistryLocationScope;

class InventoryMovement extends Model
{
    use HasUuids;

    protected $table = 'gov_inventory_movements';
    
    // Only created_at is maintained for immutability
    const UPDATED_AT = null; 

    protected $fillable = [
        'stockable_type', 'stockable_id', 'movement_type', 'quantity',
        'document_type', 'document_id', 'company_id', 'location_id', 'created_by'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new MinistryLocationScope());
    }

    public function stockable()
    {
        return $this->morphTo();
    }

    public function document()
    {
        return $this->morphTo();
    }
}