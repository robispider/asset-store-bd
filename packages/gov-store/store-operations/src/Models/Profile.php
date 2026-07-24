<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use GovStore\StoreOperations\Enums\PolicyStatus;

class Profile extends Model
{
    protected $table = 'gov_profiles';

    protected $fillable = [
        'name', 'scope', 'owner_type', 'owner_id', 
        'status', 'version', 'company_id', 'location_id'
    ];

    protected $casts = [
        'status' => PolicyStatus::class, // Auto-casts to the PHP Enum
    ];

    public function capabilities()
    {
        return $this->hasMany(ProfileCapability::class, 'profile_id');
    }

    public function assignments()
    {
        return $this->hasMany(ProfileAssignment::class, 'profile_id');
    }
}