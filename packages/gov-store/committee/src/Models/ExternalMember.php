<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalMember extends Model
{
    protected $table = 'gov_committee_external_members';
    protected $guarded = ['id'];
    protected $casts = ['expected_to_onboard' => 'boolean'];

}

