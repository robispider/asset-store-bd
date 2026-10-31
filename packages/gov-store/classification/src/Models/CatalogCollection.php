<?php
// src/Models/CatalogCollection.php
namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogCollection extends Model
{
    protected $table = 'gov_catalog_collections';
    protected $guarded = ['id'];

    public function getDisplayNameAttribute(): string
    {
        return app()->getLocale() === 'bn-BD' && $this->name_bn ? $this->name_bn : $this->name;
    }

    public function getDisplayDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'bn-BD' && $this->description_bn ? $this->description_bn : $this->description;
    }

    public function nodes()
    {
        return $this->hasMany(CatalogCollectionNode::class, 'collection_id');
    }
}
