<?php

namespace GovStore\OfficeMembership\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Location;

class OfficeMembership extends Model
{
    protected $table = 'gov_office_memberships';

    protected $fillable = [
        'user_id',
        'location_id',
        'is_home_office',
        'status',
        'valid_until',
    ];

    protected $casts = [
        'is_home_office' => 'boolean',
        'valid_until' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}