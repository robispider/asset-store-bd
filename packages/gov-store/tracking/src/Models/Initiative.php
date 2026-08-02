<?php

namespace GovStore\Tracking\Models;

use App\Models\Company;
use GovStore\Tracking\Scopes\InitiativeScope; // Added Import
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
    ];

    protected $casts = [
        'require_documents' => 'boolean',
        'allow_overshoot'   => 'boolean',
        'require_metadata'  => 'boolean',
    ];

    /**
     * Boot the model and apply the global isolation scope.
     */
    protected static function booted()
    {
        static::addGlobalScope(new InitiativeScope());
    }

    public function ownerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'owner_company_id');
    }

    public function trackingCodes(): HasMany
    {
        return $this->hasMany(TrackingCode::class, 'initiative_id');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(TrackingTimeline::class, 'initiative_id');
    }

    public function operationUnits(): HasMany
    {
        return $this->hasMany(OperationUnit::class, 'initiative_id');
    }

    /**
     * Helper to verify if the Operation Unit meets the minimum 
     * readiness requirements to be activated (1 Head, >=1 Officer).
     */
    public function isOperationallyReady(): bool
    {
        $headCount = $this->operationUnits()->where('designation', 'HEAD')->count();
        $officerCount = $this->operationUnits()->where('designation', 'OFFICER')->count();

        return ($headCount === 1 && $officerCount >= 1);
    }
}