<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TrackingAssociation extends Model
{
    protected $table = 'gov_tracking_associations';

    protected $fillable = [
        'tracking_code_id',
        'associatable_type',
        'associatable_id',
        'status',
    ];

    public function trackingCode(): BelongsTo
    {
        return $this->belongsTo(TrackingCode::class, 'tracking_code_id');
    }

    public function associatable(): MorphTo
    {
        return $this->morphTo();
    }
}