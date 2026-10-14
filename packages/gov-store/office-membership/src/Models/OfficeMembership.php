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
        'destination_location_id',
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

    /** Where a leaving employee said they are going (plan D3). */
    public function destination()
    {
        return $this->belongsTo(Location::class, 'destination_location_id')->withoutGlobalScopes();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id')->withoutGlobalScope(UserScope::class);
    }

    /** Status key for <x-gs::status-badge>: a released home office is "Ready to transfer", not "Left". */
    public function statusKey(): string
    {
        $status = $this->status === 'released' && $this->is_home_office ? 'released_home' : $this->status;

        return 'membership_'.$status;
    }

    /** Active, and not past its end date. */
    public function isLive(): bool
    {
        return $this->status === 'active' && (! $this->valid_until || $this->valid_until->gte(today()));
    }
}
