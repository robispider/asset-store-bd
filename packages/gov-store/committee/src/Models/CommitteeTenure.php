<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class CommitteeTenure extends Model
{
    protected $table = 'gov_committee_tenures';
    protected $guarded = ['id'];
    protected $casts = ['is_external' => 'boolean'];
    public function seat() { return $this->belongsTo(CommitteeSeat::class,'seat_id'); }
}
