<?php

namespace GovStore\Organization\Models;

use Illuminate\Database\Eloquent\Model;

class MinistryDirectory extends Model
{
    protected $table = 'gov_ministries_directory';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'bn_name',
        'en_name',
        'org_type',
        'parent_id',
        'hid',
        'domain',
        'company_id',
    ];

    /**
     * Get the parent node in the hierarchy.
     */
    public function parent()
    {
        return $this->belongsTo(MinistryDirectory::class, 'parent_id', 'id');
    }

    /**
     * Get all child nodes.
     */
    public function children()
    {
        return $this->hasMany(MinistryDirectory::class, 'parent_id', 'id');
    }

    /**
     * Get the linked Snipe-IT Company entity.
     */
    public function company()
    {
        return $this->belongsTo(\App\Models\Company::class, 'company_id', 'id');
    }

    /**
     * Get the full hierarchical path as a readable string.
     */
    public function getHierarchyPathAttribute(): string
    {
        $parents = [];
        $current = $this;

        while ($current->parent_id) {
            $parents[] = $current->parent;
            $current = $current->parent;
        }

        return implode(' > ', array_reverse(array_map(fn($p) => $p->en_name, $parents)))
             . ($this->en_name ? ' > ' . $this->en_name : '');
    }

    /**
     * Get children as a flat tree for dropdown/rendering.
     */
    public static function getTree(): array
    {
        $roots = self::whereNull('parent_id')->orderBy('id')->get();
        return static::buildTree($roots);
    }

    protected static function buildTree($items, $parentId = null): array
    {
        $tree = [];

        foreach ($items as $item) {
            $children = static::buildTree($items, $item->id);
            $node = [
                'id' => $item->id,
                'en_name' => $item->en_name,
                'bn_name' => $item->bn_name,
                'org_type' => $item->org_type,
                'hid' => $item->hid,
                'domain' => $item->domain,
                'children' => $children,
            ];
            $tree[] = $node;
        }

        return $tree;
    }
}
