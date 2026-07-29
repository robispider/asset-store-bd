<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackingType extends Model
{
    protected $table = 'gov_tracking_types';

    protected $fillable = [
        'code',
        'display_name',
        'icon',
        'color',
        'validation_policy',
    ];

    public function references(): HasMany
    {
        return $this->hasMany(TrackingReference::class, 'tracking_type_id');
    }
}
