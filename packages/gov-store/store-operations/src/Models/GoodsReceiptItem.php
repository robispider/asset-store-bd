<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $table = 'gov_goods_receipt_items';
    public $timestamps = false;
    
    protected $fillable = [
        'goods_receipt_id', 'stockable_type', 'stockable_id', 'quantity', 'unit_cost'
    ];

    public function stockable()
    {
        return $this->morphTo();
    }
}
