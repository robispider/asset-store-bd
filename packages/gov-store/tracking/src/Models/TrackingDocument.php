<?php

namespace GovStore\Tracking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TrackingDocument extends Model
{
    protected $table = 'gov_tracking_documents';

    protected $fillable = [
        'tracking_reference_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    protected static function booted()
    {
        static::deleting(function (TrackingDocument $document) {
            if (Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }
        });
    }

    public function reference(): BelongsTo
    {
        return $this->belongsTo(TrackingReference::class, 'tracking_reference_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
