<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeSeat extends Model
{
    protected $table = 'gov_committee_seats';
    protected $guarded = ['id'];
    protected $casts = ['is_required' => 'boolean'];
    public function role() { return $this->belongsTo(SeatRole::class, 'seat_role_code', 'code'); }
}

