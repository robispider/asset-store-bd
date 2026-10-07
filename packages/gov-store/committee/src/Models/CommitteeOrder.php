<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeOrder extends Model
{
    protected $table = 'gov_committee_orders';
    protected $guarded = ['id'];
    protected $casts = [];
    public $timestamps = false;
}

