<?php

namespace GovStore\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use GovStore\GeoAreas\Models\GeoArea;

class IctJurisdiction extends Model
{
    protected $table = 'gov_ict_jurisdictions';

    protected $fillable = [
        'user_id',
        'geo_area_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function geoArea()
    {
        return $this->belongsTo(GeoArea::class, 'geo_area_id', 'GeoAreaId');
    }
}