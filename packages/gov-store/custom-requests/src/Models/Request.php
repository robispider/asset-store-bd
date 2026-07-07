<?php

namespace GovStore\CustomRequests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Request extends Model
{
    use SoftDeletes;

    protected $table = 'custom_service_requests';

protected $fillable = [
        'request_number',
        'requested_by',
        'approved_by',
        'request_type',
        'resolved_policy', // NEW
        'assigned_approver_id', // NEW
        'purpose',
        'justification',
        'required_by_date',
        'delivery_location_id',
        'cost_center',
        'approval_status',
        'fulfillment_status',
        'submitted_at',
        'approved_at',
        'closed_at',
    ];

protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];
    /**
     * Eloquent Boot Handler: Automatically generates an 
     * incremental sequential request number on creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $year = date('Y');
            
            // Get the highest number generated during the current year
            $lastRequest = self::whereYear('created_at', $year)
                                ->orderBy('id', 'desc')
                                ->first();

            $nextNumber = 1;
            if ($lastRequest && preg_match('/SR-\d{4}-(\d+)/', $lastRequest->request_number, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            }

            $model->request_number = 'SR-' . $year . '-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        });
    }

    public function items()
    {
        return $this->hasMany(RequestItem::class, 'request_id');
    }

    public function events()
    {
        return $this->hasMany(RequestEvent::class, 'request_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}