<?php

namespace GovStore\TenantScope\Services;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;

class ReferenceTypes
{
    public const MODELS = ['category' => Category::class, 'model' => AssetModel::class,
        'supplier' => Supplier::class, 'manufacturer' => Manufacturer::class];

    public static function key(Model $model): string
    {
        return array_search(get_class($model), self::MODELS, true)
            ?: throw new \InvalidArgumentException('Unsupported reference type.');
    }
}
