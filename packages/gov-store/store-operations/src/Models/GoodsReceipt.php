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
use GovStore\StoreOperations\Enums\DocumentState;
use App\Models\User;

class GoodsReceipt extends Model implements StoreDocumentInterface
{
    use HasUuids, HasDocumentState, HasStoreReferences, HasStoreAttachments;

    protected $table = 'gov_goods_receipts';
    
    protected $fillable = [
        'receipt_no', 'supplier_id', 'purchase_type', // Now treated conceptually as 'receiving_source'
        'reference_no', 'reference_date', 'received_by_type', 
        'committee_ref', 'status', 'company_id', 'location_id', 'created_by'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new MinistryLocationScope());
    }

    // --- Relationships ---
    
    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class, 'goods_receipt_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- StoreDocumentInterface Implementation ---

    public function getDocumentId(): string
    {
        return $this->id;
    }

    public function getDocumentType(): string
    {
        return self::class;
    }

    public function getDocumentNumber(): string
    {
        return $this->receipt_no;
    }

    public function getLineItems(): Collection
    {
        return $this->items;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCompiledProfileSnapshot(): ?array
    {
        return $this->compiled_profile_snapshot;
    }
}