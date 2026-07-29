<?php

namespace GovStore\Tracking\Models;

use App\Models\Category;
use App\Models\AssetModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingTarget extends Model
{
    protected $table = 'gov_tracking_targets';

    protected $fillable = [
        'tracking_reference_id',
        'category_id',
        'model_id',
        'planned_qty',
    ];

    public function reference(): BelongsTo
    {
        return $this->belongsTo(TrackingReference::class, 'tracking_reference_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function assetModel(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'model_id');
    }
}
