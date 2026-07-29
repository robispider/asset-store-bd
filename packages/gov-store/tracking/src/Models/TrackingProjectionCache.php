<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingProjectionCache extends Model
{
    protected $table = 'gov_tracking_projection_caches';

    protected $fillable = [
        'tracking_reference_id',
        'planned',
        'ordered',
        'received',
        'deployed',
        'disposed',
    ];

    public function reference(): BelongsTo
    {
        return $this->belongsTo(TrackingReference::class, 'tracking_reference_id');
    }
}