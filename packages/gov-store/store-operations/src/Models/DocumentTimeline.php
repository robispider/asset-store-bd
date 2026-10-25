<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTimeline extends Model
{
    protected $table = 'gov_document_timelines';
    
    // Immutable created_at, stamped by PHP so it shares the app's zone (the column's DB default may not).
    const UPDATED_AT = null;

    protected $fillable = [
        'document_type', 'document_id', 'state', 'user_id', 'notes'
    ];

    /**
     * Cast raw database timestamps to Carbon datetime instances automatically.
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function document()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}