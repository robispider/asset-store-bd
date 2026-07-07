<?php

namespace GovStore\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Location;
use App\Models\User;

class OrganizationActivityLog extends Model
{
    protected $table = 'gov_organization_activity_logs';

    public $timestamps = false;

    protected $fillable = [
        'location_id',
        'performed_by',
        'event_type',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}