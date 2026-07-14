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
        'approved_by_user_id',
        'approved_at',
        'approval_note',
    ];

    protected $casts = [
        'is_home_office' => 'boolean',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the user linked to this membership.
     * TARGETED BYPASS: Allows the administrator to load the user profile regardless of active scopes.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')
            ->withoutGlobalScope(\GovStore\TenantScope\Scopes\UserScope::class);
    }

   public function location()
    {
        return $this->belongsTo(Location::class, 'location_id')
            ->withoutGlobalScopes(); // Add this to bypass TenantScope hiding the name
    }
}