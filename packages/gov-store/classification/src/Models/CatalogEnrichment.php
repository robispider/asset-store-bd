<?php

namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogEnrichment extends Model
{
    protected $table = 'gov_catalog_enrichments';
    protected $guarded = ['id'];

    /**
     * Get the parent catalog node.
     */
    public function node()
    {
        return $this->belongsTo(CatalogNode::class, 'code', 'code');
    }
}
