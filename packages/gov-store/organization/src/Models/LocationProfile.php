<?php

namespace GovStore\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Location;
use App\Models\User;
use GovStore\GeoAreas\Models\GeoArea;

class LocationProfile extends Model
{
    protected $table = 'gov_location_profiles';

    protected $fillable = [
        'location_id',
        'geo_area_id',
        'office_admin_id',
        'lifecycle_status',
        'geo_area_verified_at',
        'geo_area_verified_by',
    ];

    protected $casts = [
        'geo_area_verified_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function geoArea()
    {
        return $this->belongsTo(GeoArea::class, 'geo_area_id', 'GeoAreaId');
    }

   public function officeAdmin()
    {
        return $this->belongsTo(\App\Models\User::class, 'office_admin_id')->withoutGlobalScopes();
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'geo_area_verified_by');
    }
}