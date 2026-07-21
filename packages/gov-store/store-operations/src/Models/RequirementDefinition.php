<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class RequirementDefinition extends Model
{
    protected $table = 'gov_requirement_definitions';
    public $timestamps = false;

    protected $fillable = ['capability_id', 'field_key', 'field_type', 'validation_rules'];

    public function capability()
    {
        return $this->belongsTo(Capability::class, 'capability_id');
    }
}