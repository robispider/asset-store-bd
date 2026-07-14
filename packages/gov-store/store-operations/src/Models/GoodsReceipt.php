<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use App\Models\User;

class GoodsReceipt extends Model
{
    use HasUuids;

    protected $table = 'gov_goods_receipts';
    
    protected $fillable = [
        'receipt_no', 'supplier_id', 'purchase_type', 'reference_no', 
        'reference_date', 'received_by_type', 'committee_ref', 'status',
        'company_id', 'location_id', 'created_by'
    ];

    protected static function booted()
    {
        // Enforce physical boundary scoping
        static::addGlobalScope(new MinistryLocationScope());
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class, 'goods_receipt_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
