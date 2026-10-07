<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeScope extends Model
{
    protected $table = 'gov_committee_scopes';
    protected $guarded = ['id'];
    protected $casts = [];

}

