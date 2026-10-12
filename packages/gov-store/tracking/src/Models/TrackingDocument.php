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
        'tracking_code_id',
        'file_name',
        'file_path',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    public function trackingCode(): BelongsTo
    {
        return $this->belongsTo(TrackingCode::class, 'tracking_code_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
