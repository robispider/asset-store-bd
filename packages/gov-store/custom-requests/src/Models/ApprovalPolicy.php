<?php

namespace GovStore\CustomRequests\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalPolicy extends Model
{
    protected $table = 'gov_approval_policies';

    protected $fillable = [
        'target_type',
        'target_id',
        'policy_name',
    ];

    /**
     * Polymorphic relation to any mapped Snipe-IT Category or Model
     */
    public function target()
    {
        return $this->morphTo();
    }
}