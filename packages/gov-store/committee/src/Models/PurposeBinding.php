<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class PurposeBinding extends Model
{
    protected $table = 'gov_committee_purpose_bindings';
    protected $guarded = ['id'];
    protected $casts = ['allow_ancestor_fallback' => 'boolean', 'is_active' => 'boolean'];

}

