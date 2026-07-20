<?php

namespace GovStore\UserOnboarding\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\OfficeMembership\Models\OfficeMembership;

class UserOnboarding extends Model
{
    protected $table = 'gov_user_onboardings';

    protected $fillable = [
        'user_id', 'status', 'creator_user_id', 'owner_type', 
        'owner_id', 'geo_area_id', 'assigned_membership_id'
    ];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'creator_user_id'); }
    public function geoArea() { return $this->belongsTo(GeoArea::class, 'geo_area_id', 'GeoAreaId'); }
    public function membership() { return $this->belongsTo(OfficeMembership::class, 'assigned_membership_id'); }
}
