<?php

namespace GovStore\Tracking\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingDelivery extends Model
{
    protected $table = 'gov_tracking_deliveries';
    public $timestamps = false;
    protected $guarded = ['*'];
}
