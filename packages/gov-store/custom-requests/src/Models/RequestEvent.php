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

    // Immutable created_at only. PHP stamps it (not the column's DB default), so history times use
    // the same clock and zone as submitted_at and the other request times.
    const UPDATED_AT = null;

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
