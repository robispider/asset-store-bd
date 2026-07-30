<?php

namespace GovStore\Tracking\Models;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingTarget extends Model
{
    protected $table = 'gov_tracking_targets';

    protected $fillable = [
        'tracking_code_id',
        'category_id',
        'planned_qty',
        'economic_code',
    ];

    public function trackingCode(): BelongsTo
    {
        return $this->belongsTo(TrackingCode::class, 'tracking_code_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}