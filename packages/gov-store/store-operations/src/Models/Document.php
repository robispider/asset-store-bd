<?php

namespace GovStore\StoreOperations\Models;

use App\Models\User;
use GovStore\StoreOperations\Contracts\StoreDocumentInterface;
use GovStore\StoreOperations\Traits\HasDocumentState;
use GovStore\StoreOperations\Traits\HasStoreAttachments;
// Import the core Document Engine traits
use GovStore\StoreOperations\Traits\HasStoreReferences;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Document extends Model implements StoreDocumentInterface
{
    // Use the core traits to unlock timelines, attachments, and state validation
    use HasDocumentState, HasStoreAttachments, HasStoreReferences, HasUuids;

    protected $table = 'gov_documents';

    protected $fillable = [
        'document_number', 'type', 'status', 'compiled_profile_snapshot',
        'company_id', 'location_id', 'created_by', 'reference_no', 'reference_date', 'purchase_type',
        'drafted_by', 'posted_by', 'posted_at', 'managed_by',
        'source_document_id', 'adjustment_reason', 'issued_to_user_id', 'issue_department',
        'supplier_id',
        'destination_location_id', 'transfer_reason',
    ];

    protected $casts = [
        'compiled_profile_snapshot' => 'array',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new MinistryLocationScope);
    }

    // --- Relationships ---

    public function items()
    {
        return $this->hasMany(DocumentItem::class, 'document_id');
    }

    public function timelines()
    {
        return $this->morphMany(DocumentTimeline::class, 'document');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplier()
    {
        return $this->belongsTo(\App\Models\Supplier::class);
    }

    public function drafter()
    {
        return $this->belongsTo(User::class, 'drafted_by')->withoutGlobalScopes();
    }

    public function poster()
    {
        return $this->belongsTo(User::class, 'posted_by')->withoutGlobalScopes();
    }

    // --- StoreDocumentInterface Implementation ---

    public function getDocumentId(): string|int
    {
        return $this->id;
    }

    public function getDocumentType(): string
    {
        return $this->type;
    }

    public function getDocumentNumber(): string
    {
        return $this->document_number;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLineItems(): Collection
    {
        return $this->items;
    }

    public function getCompiledProfileSnapshot(): ?array
    {
        return $this->compiled_profile_snapshot;
    }
}
