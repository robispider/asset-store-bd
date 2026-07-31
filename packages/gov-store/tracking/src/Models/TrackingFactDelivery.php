<?php

namespace GovStore\Tracking\Models;

use App\Models\Category;
use App\Models\AssetModel;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Models\Location;
use GovStore\GeoAreas\Models\GeoArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackingFactDelivery extends Model
{
    protected $table = 'gov_tracking_fact_deliveries';

    protected $fillable = [
        'initiative_id',
        'tracking_code_id',
        'funding_type_id',
        'fiscal_year',
        'location_id',
        'geo_area_id',
        'category_id',
        'model_id',
        'manufacturer_id',
        'supplier_id',
        'received_qty',
        'total_cost',
        'transaction_count',
    ];

    protected $casts = [
        'received_qty' => 'integer',
        'total_cost' => 'decimal:2',
        'transaction_count' => 'integer',
    ];

    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class, 'initiative_id');
    }

    public function trackingCode(): BelongsTo
    {
        return $this->belongsTo(TrackingCode::class, 'tracking_code_id');
    }

    public function fundingType(): BelongsTo
    {
        return $this->belongsTo(FundingType::class, 'funding_type_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function geoArea(): BelongsTo
    {
        return $this->belongsTo(GeoArea::class, 'geo_area_id', 'GeoAreaId');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function assetModel(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'model_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class, 'manufacturer_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}