<?php

namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Category;

class CatalogNode extends Model
{
    protected $table = 'gov_catalog_nodes';
    protected $guarded = ['id'];

    /**
     * Level constants for tree hierarchy.
     */
    const LEVEL_SEGMENT  = 1;
    const LEVEL_FAMILY   = 2;
    const LEVEL_CLASS    = 3;
    const LEVEL_COMMODITY = 4;

    /**
     * Reference relation: definition_en for this node.
     */
    public function definition()
    {
        return $this->hasOne(CatalogDefinition::class, 'code', 'code');
    }

    /**
     * Operational relation: Bangla enrichment (soft-linked via code).
     */
    public function enrichment()
    {
        return $this->hasOne(CatalogEnrichment::class, 'code', 'code');
    }

    /**
     * Operational relation: synonyms (soft-linked via code).
     */
    public function synonyms()
    {
        return $this->hasMany(CatalogSynonym::class, 'code', 'code');
    }

    /**
     * Operational relation: Snipe-IT category mapping (soft-linked via code).
     */
    public function snipeMapping()
    {
        return $this->hasOne(CatalogSnipeMapping::class, 'code', 'code');
    }

    /**
     * Scope to a specific scheme.
     */
    public function scopeByScheme($query, string $scheme)
    {
        return $query->where('scheme', $scheme);
    }

    /**
     * Scope to selectable nodes only.
     */
    public function scopeSelectable($query)
    {
        return $query->where('is_selectable', true);
    }

    /**
     * Scope by level constant.
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get display title in the given language.
     */
    public function getDisplayTitle(?string $locale = null): string
    {
        if ($locale === 'bn' && $this->enrichment && !empty($this->enrichment->title_bn)) {
            return $this->enrichment->title_bn;
        }
        return $this->title_en;
    }

    /**
     * Check if this node is at the top-level (Segment).
     */
    public function isRoot(): bool
    {
        return $this->level === self::LEVEL_SEGMENT;
    }
}
