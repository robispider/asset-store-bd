<?php

namespace GovStore\OfficeMembership\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Location;

class OfficeResponsibility extends Model
{
    protected $table = 'gov_office_responsibilities';

    protected $fillable = [
        'location_id',
        'user_id',
        'role_slug',
    ];

    /**
     * Get the user who holds this responsibility in the active office.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScope(\GovStore\TenantScope\Scopes\UserScope::class);
    }

    /**
     * Get the office location where this responsibility applies.
     */
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}