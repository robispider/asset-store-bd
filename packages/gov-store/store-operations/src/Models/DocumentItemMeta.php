<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentItemMeta extends Model
{
    protected $table = 'gov_document_item_meta';
    public $timestamps = false;

    protected $fillable = ['document_item_id', 'field_key', 'value', 'row_index'];

    public function item()
    {
        return $this->belongsTo(DocumentItem::class, 'document_item_id');
    }
}