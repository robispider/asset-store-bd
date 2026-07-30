<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingScope extends Model
{
    protected $table = 'gov_tracking_scopes';

    protected $fillable = [
        'tracking_code_id',
        'dimension',
        'target_type',
        'target_id',
    ];

    public function trackingCode(): BelongsTo
    {
        return $this->belongsTo(TrackingCode::class, 'tracking_code_id');
    }
}