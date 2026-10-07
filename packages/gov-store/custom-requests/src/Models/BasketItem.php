<?php

namespace GovStore\CustomRequests\Models;

use App\Models\AssetModel;
use GovStore\CustomRequests\Support\RequestWorkflow;
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
        // Old malformed drafts remain removable without resolving arbitrary class names.
        if (! in_array($this->requested_type, RequestWorkflow::TYPES)) {
            return $this->belongsTo(AssetModel::class, 'requested_id')->whereRaw('1 = 0');
        }

        return $this->morphTo('requested', 'requested_type', 'requested_id');
    }
}
