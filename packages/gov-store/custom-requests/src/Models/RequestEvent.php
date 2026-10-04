<?php

namespace GovStore\CustomRequests\Models;

use App\Models\User;
use GovStore\CustomRequests\Services\RequestNotices;
use Illuminate\Database\Eloquent\Model;

class RequestEvent extends Model
{
    protected static function booted(): void
    {
        static::created(fn ($event) => app(RequestNotices::class)->record($event));
    }

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
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
