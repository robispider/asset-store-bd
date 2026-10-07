<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class SeatRole extends Model
{
    protected $table = 'gov_committee_seat_roles';
    protected $guarded = ['id'];
    protected $casts = ['is_presiding' => 'boolean', 'is_secretary' => 'boolean', 'counts_toward_strength' => 'boolean', 'is_active' => 'boolean'];

}

