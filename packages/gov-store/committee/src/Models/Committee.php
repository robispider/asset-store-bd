<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class Committee extends Model
{
    protected $table = 'gov_committees';
    protected $guarded = ['id'];
    protected $casts = ['version_no' => 'integer', 'lock_version' => 'integer', 'policy_snapshot' => 'array'];

    use \Illuminate\Database\Eloquent\Concerns\HasUuids;
    use \Illuminate\Database\Eloquent\SoftDeletes;
    public function type() { return $this->belongsTo(CommitteeType::class, 'committee_type_id'); }
    public function seats() { return $this->hasMany(CommitteeSeat::class); }
    public function tenures() { return $this->hasMany(CommitteeTenure::class); }
    public function orders() { return $this->hasMany(CommitteeOrder::class); }
    public function scopes() { return $this->hasMany(CommitteeScope::class); }

}

