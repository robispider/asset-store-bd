<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class TrackingCode extends Model
{
    protected $table = 'gov_tracking_codes';

    protected $fillable = [
        'initiative_id',
        'funding_type_id',
        'tracking_code',
        'task_title',
        'specificity_level', // Added
        'fiscal_year',
        'status',
        'order_pdf_path',
    ];

    protected static function booted()
    {
        static::deleting(function (TrackingCode $code) {
            if ($code->order_pdf_path && Storage::disk('local')->exists($code->order_pdf_path)) {
                Storage::disk('local')->delete($code->order_pdf_path);
            }
        });
    }

    // ... [Relationships remain unchanged]
    
    public function initiative(): BelongsTo { return $this->belongsTo(Initiative::class, 'initiative_id'); }
    public function fundingType(): BelongsTo { return $this->belongsTo(FundingType::class, 'funding_type_id'); }
    public function targets(): HasMany { return $this->hasMany(TrackingTarget::class, 'tracking_code_id'); }
    public function scopes(): HasMany { return $this->hasMany(TrackingScope::class, 'tracking_code_id'); }
    public function associations(): HasMany { return $this->hasMany(TrackingAssociation::class, 'tracking_code_id'); }
}