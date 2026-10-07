<?php

namespace GovStore\OfficeMembership\Models;

use App\Models\Location;
use App\Models\User;
use GovStore\TenantScope\Scopes\UserScope;
use Illuminate\Database\Eloquent\Model;

class OfficeMembership extends Model
{
    protected $table = 'gov_office_memberships';

    protected $fillable = [
        'valid_until',
        'user_id',
        'location_id',
        'is_home_office',
        'status',
        'approved_by_user_id',
        'approved_at',
        'approval_note',
    ];

    protected $casts = [
        'valid_until' => 'date',
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
            ->withoutGlobalScope(UserScope::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id')
            ->withoutGlobalScopes(); // Add this to bypass TenantScope hiding the name
    }
}
