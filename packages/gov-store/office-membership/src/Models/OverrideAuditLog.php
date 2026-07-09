<?php
namespace GovStore\OfficeMembership\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class OverrideAuditLog extends Model
{
    protected $table = 'gov_override_audit_logs';
    protected $fillable = ['target_user_id', 'override_type', 'reason', 'executed_by', 'old_location_id', 'new_location_id'];

    public function targetUser() { return $this->belongsTo(User::class, 'target_user_id'); }
    public function executor() { return $this->belongsTo(User::class, 'executed_by'); }
}