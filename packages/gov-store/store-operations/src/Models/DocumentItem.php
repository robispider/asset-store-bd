<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DocumentItem extends Model
{
    use HasUuids;

    protected $table = 'gov_document_items';
    public $timestamps = false;

    protected $fillable = ['document_id', 'product_type', 'product_id', 'quantity', 'unit_cost'];

    // 1. Append flat attributes
    protected $appends = ['product_name', 'current_stock'];

    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    public function metadata()
    {
        return $this->hasMany(DocumentItemMeta::class, 'document_item_id');
    }

    /**
     * 2. Rename the polymorphic method to 'product' 
     * Native Laravel: no parameters needed since it matches product_type/product_id!
     */
    public function product()
    {
        return $this->morphTo();
    }

    // --- Accessors (Defensively loads the 'product' relation) ---

    public function getProductNameAttribute()
    {
        if (!$this->relationLoaded('product') && $this->product_type && $this->product_type !== '0') {
            $this->loadMissing('product');
        }

        return $this->product ? $this->product->name : 'Unknown Product';
    }

    public function getCurrentStockAttribute()
    {
        if (!$this->relationLoaded('product') && $this->product_type && $this->product_type !== '0') {
            $this->loadMissing('product');
        }

        return $this->product ? (int) $this->product->qty : 0;
    }
}