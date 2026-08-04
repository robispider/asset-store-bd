<?php
// src/Models/CatalogCollection.php
namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogCollection extends Model
{
    protected $table = 'gov_catalog_collections';
    protected $guarded = ['id'];

    public function nodes()
    {
        return $this->hasMany(CatalogCollectionNode::class, 'collection_id');
    }
}
