<?php

namespace GovStore\Tracking\Models;

use App\Models\Category;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TrackingAssociation extends Model
{
    protected $table = 'gov_tracking_associations';

    protected $fillable = [
        'tracking_code_id',
        'delivery_id',
        'category_id',
        'location_id',
        'quantity',
        'associatable_type',
        'associatable_id',
        'status',
    ];

    public function trackingCode(): BelongsTo
    {
        return $this->belongsTo(TrackingCode::class, 'tracking_code_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function associatable(): MorphTo
    {
        return $this->morphTo();
    }
}
