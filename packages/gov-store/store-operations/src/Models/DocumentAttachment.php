<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DocumentAttachment extends Model
{
    use HasUuids;

    protected $table = 'gov_document_attachments';

    protected $fillable = [
        'document_type', 'document_id', 'file_path', 'original_name', 'mime_type', 'uploaded_by'
    ];

    public function document()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}