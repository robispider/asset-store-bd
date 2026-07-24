<?php

namespace GovStore\StoreOperations\Models;

use Illuminate\Database\Eloquent\Model;
use GovStore\StoreOperations\Enums\CapabilityBehavior;

class ProfileCapability extends Model
{
    protected $table = 'gov_profile_capabilities';
    public $timestamps = false;

    protected $fillable = [
        'profile_id', 'capability_code', 'behavior', 'config_payload'
    ];

    protected $casts = [
        'behavior' => CapabilityBehavior::class, // Auto-casts to ENFORCE/DISABLE/INHERIT
        'config_payload' => 'array'
    ];

    public function profile()
    {
        return $this->belongsTo(Profile::class, 'profile_id');
    }
}