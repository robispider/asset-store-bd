<?php

namespace GovStore\CustomRequests\Models;

use Illuminate\Database\Eloquent\Model;

class RequestItem extends Model
{
    protected $table = 'custom_service_request_items';

    protected $fillable = [
        'request_id',
        'requested_type',
        'requested_id',
        'fulfilled_type',
        'fulfilled_id',
        'requested_qty',
        'approved_qty',
        'reserved_qty',
        'issued_qty',
        'line_approval_status',
        'line_fulfillment_status',
        'notes',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    /**
     * Polymorphic relation mapping to the requested catalog item
     */
    public function requested()
    {
        return $this->morphTo('requested', 'requested_type', 'requested_id');
    }

    /**
     * Polymorphic relation mapping to the actually fulfilled/substituted item
     */
    public function fulfilled()
    {
        return $this->morphTo('fulfilled', 'fulfilled_type', 'fulfilled_id');
    }
}