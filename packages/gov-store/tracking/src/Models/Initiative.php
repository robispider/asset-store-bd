<?php

namespace GovStore\Tracking\Models;

use App\Models\Company;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Initiative extends Model
{
   protected $table = 'gov_initiatives';

    protected $fillable = [
        'title',
        'purpose',
        'status',
        'primary_funding',
        'require_documents',
        'allow_overshoot',
        'require_metadata',
        'owner_company_id',
        'manager_location_id',
    ];

    protected $casts = [
        'require_documents' => 'boolean',
        'allow_overshoot' => 'boolean',
        'require_metadata' => 'boolean',
    ];

    public function ownerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function managingOffice(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'manager_location_id');
    }

    public function trackingCodes(): HasMany
    {
        return $this->hasMany(TrackingCode::class, 'initiative_id');
    }
    public function timeline(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrackingTimeline::class, 'initiative_id');
    }
}