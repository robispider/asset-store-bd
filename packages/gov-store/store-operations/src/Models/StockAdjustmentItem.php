<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentItem extends Model
{
    protected $table = 'gov_stock_adjustment_items';
    public $timestamps = false;
    
    protected $fillable = [
        'stock_adjustment_id', 'stockable_type', 'stockable_id', 'direction', 'quantity'
    ];

    public function stockable()
    {
        return $this->morphTo();
    }
}
