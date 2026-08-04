<?php

// src/Models/CatalogCollectionNode.php
namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogCollectionNode extends Model
{
    protected $table = 'gov_catalog_collection_nodes';
    protected $guarded = ['id'];

    public function collection()
    {
        return $this->belongsTo(CatalogCollection::class, 'collection_id');
    }

    public function catalogNode()
    {
        return $this->belongsTo(CatalogNode::class, 'code', 'code');
    }
}