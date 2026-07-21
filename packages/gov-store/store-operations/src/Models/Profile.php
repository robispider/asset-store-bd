<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $table = 'gov_profiles';

    protected $fillable = ['parent_id', 'name'];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function capabilities()
    {
        return $this->belongsToMany(Capability::class, 'gov_profile_capabilities', 'profile_id', 'capability_id')
                    ->withPivot('config_payload');
    }
}