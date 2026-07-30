<?php

namespace GovStore\Tracking\Models;

use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingAllocation extends Model
{
    protected $table = 'gov_tracking_allocations';

    protected $fillable = [
        'target_id',
        'location_id',
        'allocated_qty',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(TrackingTarget::class, 'target_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}