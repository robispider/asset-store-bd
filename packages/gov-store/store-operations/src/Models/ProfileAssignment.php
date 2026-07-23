<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ProfileAssignment extends Model
{
    protected $table = 'gov_profile_assignments';

    protected $fillable = [
        'profile_id', 'target_type', 'target_id', 'assigned_by', 
        'effective_from', 'effective_to'
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to'   => 'datetime',
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }

    /**
     * The entity that adopted this policy (e.g. App\Models\Category)
     */
    public function target()
    {
        return $this->morphTo();
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}