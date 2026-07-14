<?php

namespace GovStore\OfficeMembership\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Location;

class RoleHandshake extends Model
{
    protected $table = 'gov_role_handshakes';

    protected $fillable = [
        'location_id',
        'role_type', // The physical database column
        'outgoing_user_id',
        'incoming_user_id',
        'status',
    ];

    // =========================================================================
    // DOMAIN ALIGNMENT: Accessors mapping legacy columns to domain language
    // =========================================================================
    
    public function getRoleSlugAttribute()
    {
        return $this->attributes['role_type'] ?? null;
    }

    public function setRoleSlugAttribute($value)
    {
        $this->attributes['role_type'] = $value;
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function outgoingUser()
    {
        return $this->belongsTo(User::class, 'outgoing_user_id')
            ->withoutGlobalScope(\GovStore\TenantScope\Scopes\UserScope::class);
    }

    public function incomingUser()
    {
        return $this->belongsTo(User::class, 'incoming_user_id')
            ->withoutGlobalScope(\GovStore\TenantScope\Scopes\UserScope::class);
    }
}