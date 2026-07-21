<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Collection;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use GovStore\StoreOperations\Contracts\StoreDocumentInterface;
use GovStore\StoreOperations\Traits\HasDocumentState;
use GovStore\StoreOperations\Traits\HasStoreReferences;
use GovStore\StoreOperations\Traits\HasStoreAttachments;

class GoodsIssue extends Model implements StoreDocumentInterface
{
    use HasUuids, HasDocumentState, HasStoreReferences, HasStoreAttachments;

    protected $table = 'gov_goods_issues';

    protected $fillable = [
        'issue_no', 'issue_type', 'issued_to_id',
        'reference_type', 'reference_id', 'status',
        'company_id', 'location_id', 'created_by'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new MinistryLocationScope());
    }

    public function items()
    {
        return $this->hasMany(GoodsIssueItem::class, 'goods_issue_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // --- StoreDocumentInterface Implementation ---

    public function getDocumentId(): string|int { return $this->id; }
    public function getDocumentType(): string { return 'issue'; }
    public function getDocumentNumber(): string { return $this->issue_no; }
    public function getLineItems(): Collection { return $this->items; }

    // --- Added missing interface methods to resolve compiler exception ---

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCompiledProfileSnapshot(): ?array
    {
        return $this->compiled_profile_snapshot;
    }
}