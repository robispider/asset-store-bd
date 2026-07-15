<?php

namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogDefinition extends Model
{
    protected $table = 'gov_catalog_definitions';
    protected $guarded = ['id'];

    /**
     * Get the parent catalog node.
     */
    public function node()
    {
        return $this->belongsTo(CatalogNode::class, 'code', 'code');
    }
}
