<?php

namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Category;

class CatalogSnipeMapping extends Model
{
    protected $table = 'gov_catalog_snipe_mappings';
    protected $guarded = ['id'];

    /**
     * Get the parent catalog node (soft-linked via code).
     */
    public function node()
    {
        return $this->belongsTo(CatalogNode::class, 'code', 'code');
    }

    /**
     * Get the Snipe-IT Category this node maps to.
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Update or create a mapping for the given code.
     */
    public static function bind(string $code, int $categoryId): self
    {
        return static::updateOrCreate(
            ['code' => $code],
            ['category_id' => $categoryId]
        );
    }

    /**
     * Remove the Snipe-IT mapping for a code (does not delete the node).
     */
    public function unbind(): void
    {
        $this->delete();
    }
}
