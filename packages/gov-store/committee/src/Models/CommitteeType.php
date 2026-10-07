<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeType extends Model
{
    protected $table = 'gov_committee_types';
    protected $guarded = ['id'];
    protected $casts = ['composition_policy' => 'array', 'allowed_scope_types' => 'array', 'allow_concurrent' => 'boolean', 'is_active' => 'boolean'];

}

