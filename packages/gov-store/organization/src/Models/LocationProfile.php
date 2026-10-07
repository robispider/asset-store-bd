<?php

namespace GovStore\Organization\Models;

use App\Models\Location;
use App\Models\User;
use GovStore\GeoAreas\Models\GeoArea;
use Illuminate\Database\Eloquent\Model;

class LocationProfile extends Model
{
    protected $table = 'gov_location_profiles';

    protected $fillable = [
        'invitation_code',
        'invitation_code_created_at',
        'invitation_code_expires_at',
        'location_id',
        'geo_area_id',
        'office_type',
        'office_admin_id',
        'lifecycle_status',
        'geo_area_verified_at',
        'geo_area_verified_by',
    ];

    protected $casts = [
        'invitation_code_created_at' => 'datetime',
        'invitation_code_expires_at' => 'datetime',
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
        return $this->belongsTo(User::class, 'office_admin_id')->withoutGlobalScopes();
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'geo_area_verified_by');
    }
}
