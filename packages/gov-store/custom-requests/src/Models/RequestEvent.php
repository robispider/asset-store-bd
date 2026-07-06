<?php

namespace GovStore\CustomRequests\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class RequestEvent extends Model
{
    protected $table = 'custom_service_request_events';

    // Disable default timestamps because we use immutable created_at only
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'user_id',
        'event_type',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(Request::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}