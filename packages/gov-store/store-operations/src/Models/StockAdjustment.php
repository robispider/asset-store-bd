<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use App\Models\User;

class StockAdjustment extends Model
{
    use HasUuids;

    protected $table = 'gov_stock_adjustments';
    
    protected $fillable = [
        'adjustment_no', 'adjustment_type', 'remarks', 'status',
        'company_id', 'location_id', 'created_by'
    ];

    protected static function booted()
    {
        // Enforce physical boundary scoping
        static::addGlobalScope(new MinistryLocationScope());
    }

    public function items()
    {
        return $this->hasMany(StockAdjustmentItem::class, 'stock_adjustment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
