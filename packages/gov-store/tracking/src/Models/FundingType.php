<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingType extends Model
{
    protected $table = 'gov_funding_types';

    protected $fillable = [
        'primary_type',
        'name',
        'description',
    ];

    public function trackingCodes(): HasMany
    {
        return $this->hasMany(TrackingCode::class, 'funding_type_id');
    }
}