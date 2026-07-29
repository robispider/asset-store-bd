<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingReference extends Model
{
    protected $table = 'gov_tracking_references';

    protected $fillable = [
        'tracking_type_id',
        'reference_code',
        'title',
        'description',
        'status',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function trackingType(): BelongsTo
    {
        return $this->belongsTo(TrackingType::class, 'tracking_type_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TrackingDocument::class, 'tracking_reference_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TrackingTarget::class, 'tracking_reference_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(TrackingScope::class, 'tracking_reference_id');
    }
}
