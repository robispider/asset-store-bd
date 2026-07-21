<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class Capability extends Model
{
    protected $table = 'gov_capabilities';
    public $timestamps = false;

    protected $fillable = ['code', 'type'];

    public function requirements()
    {
        return $this->hasMany(RequirementDefinition::class, 'capability_id');
    }
}