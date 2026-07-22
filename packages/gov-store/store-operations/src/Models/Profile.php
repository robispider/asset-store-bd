<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'gov_profiles';

    protected $fillable = ['parent_id', 'name', 'layer'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * FIXED: Clean hasMany mapping directly to the composed capability records.
     * Completely removes the obsolete belongsToMany/pivot dependencies.
     */
    public function capabilities()
    {
        return $this->hasMany(ProfileCapability::class, 'profile_id');
    }
}