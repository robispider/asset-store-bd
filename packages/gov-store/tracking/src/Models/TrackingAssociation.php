<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TrackingAssociation extends Model
{
    protected $table = 'gov_tracking_associations';

    protected $fillable = [
        'tracking_reference_id',
        'associatable_type',
        'associatable_id',
        'status',
    ];

    public function reference(): BelongsTo
    {
        return $this->belongsTo(TrackingReference::class, 'tracking_reference_id');
    }

    public function associatable(): MorphTo
    {
        return $this->morphTo();
    }
}
