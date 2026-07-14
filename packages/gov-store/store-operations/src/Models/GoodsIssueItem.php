<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsIssueItem extends Model
{
    protected $table = 'gov_goods_issue_items';
    public $timestamps = false;
    
    protected $fillable = [
        'goods_issue_id', 'stockable_type', 'stockable_id', 'quantity'
    ];

    public function stockable()
    {
        return $this->morphTo();
    }
}
