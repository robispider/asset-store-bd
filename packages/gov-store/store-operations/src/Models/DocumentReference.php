<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentReference extends Model
{
    protected $table = 'gov_document_references';

    protected $fillable = [
        'document_type', 'document_id', 'reference_type', 'reference_number', 'reference_date'
    ];

    public function document()
    {
        return $this->morphTo();
    }
}