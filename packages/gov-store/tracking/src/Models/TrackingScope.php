<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingScope extends Model
{
    protected $table = 'gov_tracking_scopes';

    protected $fillable = [
        'tracking_reference_id',
        'dimension',
        'target_type',
        'target_id',
    ];

    public function reference(): BelongsTo
    {
        return $this->belongsTo(TrackingReference::class, 'tracking_reference_id');
    }

    /**
     * Resolve the readable presentation details for the scope target.
     */
    public function getTargetDisplayNameAttribute(): string
    {
        if ($this->target_type === 'Global') {
            return 'Platform-Wide';
        }

        try {
            switch ($this->target_type) {
                case 'Company':
                    return \App\Models\Company::find($this->target_id)?->name ?? "Ministry #{$this->target_id}";
                case 'Location':
                    return \App\Models\Location::find($this->target_id)?->name ?? "Office #{$this->target_id}";
                case 'GeoArea':
                    if (class_exists('GovStore\GeoAreas\Models\GeoArea')) {
                        return \GovStore\GeoAreas\Models\GeoArea::find($this->target_id)?->en_name ?? "GeoArea #{$this->target_id}";
                    }
                    return "GeoArea #{$this->target_id}";
            }
        } catch (\Exception $e) {
            // Graceful fallback for missing relational environments
        }

        return "Unknown Target ({$this->target_type} #{$this->target_id})";
    }
}
