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
        'role_type',
        'outgoing_user_id',
        'incoming_user_id',
        'status',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function outgoingUser()
    {
        return $this->belongsTo(User::class, 'outgoing_user_id');
    }

    public function incomingUser()
    {
        return $this->belongsTo(User::class, 'incoming_user_id');
    }
}