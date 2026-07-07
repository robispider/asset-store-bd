<?php

namespace GovStore\GeoAreas\Models;

use Illuminate\Database\Eloquent\Model;

class GeoArea extends Model
{
    protected $table = 'gov_geo_areas';

    // Specify the custom primary key from your source SQL table
    protected $primaryKey = 'GeoAreaId';

    // Disable auto-incrementing since IDs are explicitly mapped during imports
    public $incrementing = false;

    protected $fillable = [
        'GeoAreaId',
        'hid',
        'geo_type',
        'geo_code',
        'parent_geo_code',
        'bn_name',
        'en_name',
        'GeoLevel',
    ];
}