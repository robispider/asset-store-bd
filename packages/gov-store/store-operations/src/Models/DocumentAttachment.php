<?php

namespace GovStore\StoreOperations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DocumentAttachment extends Model
{
    use HasUuids;

    protected $table = 'gov_document_attachments';

    protected $fillable = [
        'document_type', 'document_id', 'file_path', 'original_name', 'mime_type', 'uploaded_by', 'disk',
    ];

    public function document()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
