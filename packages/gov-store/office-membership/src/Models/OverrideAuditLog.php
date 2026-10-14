<?php

namespace GovStore\OfficeMembership\Models;

use App\Models\Location;
use App\Models\User;
use GovStore\TenantScope\Scopes\UserScope;
use Illuminate\Database\Eloquent\Model;

class OverrideAuditLog extends Model
{
    protected $table = 'gov_override_audit_logs';

    protected $fillable = ['target_user_id', 'override_type', 'reason', 'reference', 'details', 'executed_by', 'old_location_id', 'new_location_id'];

    protected $casts = ['details' => 'array'];

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id')->withoutGlobalScope(UserScope::class);
    }

    public function executor()
    {
        return $this->belongsTo(User::class, 'executed_by')->withoutGlobalScope(UserScope::class);
    }

    public function office()
    {
        return $this->belongsTo(Location::class, 'old_location_id')->withoutGlobalScopes();
    }
}
