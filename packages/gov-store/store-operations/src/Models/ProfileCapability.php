<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileCapability extends Model
{
    protected $table = 'gov_profile_capabilities';
    public $timestamps = false;

    protected $fillable = ['profile_id', 'capability_code', 'config_payload'];

    /**
     * Cast JSON payload natively to array when retrieved.
     */
    protected $casts = [
        'config_payload' => 'array'
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}