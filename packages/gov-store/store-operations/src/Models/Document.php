<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use GovStore\TenantScope\Scopes\MinistryLocationScope;
use GovStore\StoreOperations\Contracts\StoreDocumentInterface;
// Import the core Document Engine traits
use GovStore\StoreOperations\Traits\HasDocumentState;
use GovStore\StoreOperations\Traits\HasStoreReferences;
use GovStore\StoreOperations\Traits\HasStoreAttachments;

class Document extends Model implements StoreDocumentInterface
{
    // Use the core traits to unlock timelines, attachments, and state validation
    use HasUuids, HasDocumentState, HasStoreReferences, HasStoreAttachments;

    protected $table = 'gov_documents';

    protected $fillable = [
        'document_number', 'type', 'status', 'compiled_profile_snapshot',
        'company_id', 'location_id', 'created_by', 'reference_no', 'reference_date', 'purchase_type'
    ];

    protected $casts = [
        'compiled_profile_snapshot' => 'array'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new MinistryLocationScope());
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
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // --- StoreDocumentInterface Implementation ---

    public function getDocumentId(): string|int { return $this->id; }
    public function getDocumentType(): string { return $this->type; }
    public function getDocumentNumber(): string { return $this->document_number; }
    public function getStatus(): string { return $this->status; }
    public function getLineItems(): \Illuminate\Support\Collection { return $this->items; }
    public function getCompiledProfileSnapshot(): ?array { return $this->compiled_profile_snapshot; }
}