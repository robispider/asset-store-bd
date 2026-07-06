<?php

namespace GovStore\CustomRequests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User; // Snipe-IT core User model

class ItemRequest extends Model
{
    use SoftDeletes;

    // Point to our newly created custom table
    protected $table = 'custom_item_requests';

    protected $fillable = [
        'requestable_type',
        'requestable_id',
        'requested_by',
        'approved_by',
        'status',
        'notes',
    ];

    /**
     * Morph relationship dynamically returns the Snipe-IT Item 
     * (Asset, Consumable, Accessory, or License)
     */
    public function requestable()
    {
        return $this->morphTo();
    }

    /**
     * The employee who requested the item
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The admin who approved or rejected the item
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope for easy filtering in controllers
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }
}