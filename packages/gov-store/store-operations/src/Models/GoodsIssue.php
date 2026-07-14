<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use App\Models\User;

class GoodsIssue extends Model
{
    use HasUuids;

    protected $table = 'gov_goods_issues';
    
    protected $fillable = [
        'issue_no', 'issue_type', 'issued_to_id', 
        'reference_type', 'reference_id', 'status',
        'company_id', 'location_id', 'created_by'
    ];

    protected static function booted()
    {
        // Enforce physical boundary scoping
        static::addGlobalScope(new MinistryLocationScope());
    }

    public function items()
    {
        return $this->hasMany(GoodsIssueItem::class, 'goods_issue_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
