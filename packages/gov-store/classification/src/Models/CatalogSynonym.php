<?php

namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogSynonym extends Model
{
    protected $table = 'gov_catalog_synonyms';
    protected $guarded = ['id'];

    /**
     * Synonym type constants.
     */
    const TYPE_OFFICIAL = 'official';
    const TYPE_COMMON   = 'common';
    const TYPE_ALIAS    = 'alias';

    /**
     * Get the parent catalog node.
     */
    public function node()
    {
        return $this->belongsTo(CatalogNode::class, 'code', 'code');
    }

    /**
     * Scope to a specific language.
     */
    public function scopeByLanguage($query, string $lang)
    {
        return $query->where('language', $lang);
    }

    /**
     * Scope to a specific synonym type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
