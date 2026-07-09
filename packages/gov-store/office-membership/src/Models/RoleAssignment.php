<?php
namespace GovStore\OfficeMembership\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Location;

class RoleAssignment extends Model
{
    protected $table = 'gov_role_assignments';
    protected $fillable = ['location_id', 'role_type', 'assigned_user_id', 'assigned_by_user_id', 'status'];

    public function location() { return $this->belongsTo(Location::class, 'location_id'); }
    public function assignedUser() { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by_user_id'); }
}