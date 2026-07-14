<?php

namespace GovStore\CustomRequests\Models;

use Illuminate\Database\Eloquent\Model;

class BasketItem extends Model
{
    protected $table = 'draft_basket_items';

    protected $fillable = ['basket_id', 'requested_type', 'requested_id', 'requested_qty'];

    /**
     * The parent basket.
     */
    public function basket()
    {
        return $this->belongsTo(DraftBasket::class, 'basket_id');
    }

    /**
     * Polymorphic relation to the requested catalog item.
     */
    public function requested()
    {
        return $this->morphTo('requested', 'requested_type', 'requested_id');
    }
}
