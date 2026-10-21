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
        'bbs_code',
        'bbs_code_legacy',
        'code_status',
        'code_source',
        'bbs_attributes',
    ];

    protected $casts = [
        'bbs_attributes' => 'array',
    ];

    /** True when the area has no official BBS 2017 code yet (newer or moved area). */
    public function isProvisional(): bool
    {
        return $this->code_status === 'provisional';
    }
}
