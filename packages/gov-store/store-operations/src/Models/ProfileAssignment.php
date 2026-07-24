<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use GovStore\StoreOperations\Enums\AssignmentScope;

class ProfileAssignment extends Model
{
    protected $table = 'gov_profile_assignments';

    protected $fillable = [
        'profile_id', 'target_type', 'target_id', 
        'scope_level', 'scope_id', 
        'assigned_by', 'effective_from', 'effective_to'
    ];

    protected $casts = [
        'scope_level'    => AssignmentScope::class, // Auto-casts to GLOBAL/COMPANY/LOCATION/NATIVE
        'effective_from' => 'datetime',
        'effective_to'   => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    public function target()
    {
        return $this->morphTo();
    }
}