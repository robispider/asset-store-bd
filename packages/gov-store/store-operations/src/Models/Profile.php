<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'gov_profiles';

    protected $fillable = [
        'name', 'scope', 'owner_type', 'owner_id', 'status', 'version'
    ];

    /**
     * Polymorphic owner of the policy (e.g. System, Company, Location)
     */
    public function owner()
    {
        return $this->morphTo();
    }

    /**
     * The actual business rules (plugins) assigned to this policy.
     */
    public function capabilities()
    {
        return $this->hasMany(ProfileCapability::class, 'profile_id');
    }

    /**
     * The targets (Categories, Models, etc.) that have adopted this policy.
     */
    public function assignments()
    {
        return $this->hasMany(ProfileAssignment::class, 'profile_id');
    }
}