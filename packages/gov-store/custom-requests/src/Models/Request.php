<?php

namespace GovStore\CustomRequests\Models;

use App\Models\User;
use GovStore\CustomRequests\Services\RequestNumberService;
use GovStore\StoreOperations\Models\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    use SoftDeletes;

    protected $table = 'custom_service_requests';

    protected $fillable = [
        'request_number',
        'requested_by',
        'office_id',
        'primary_decided_by',
        'decided_by',
        'received_at',
        'return_requested_at',
        'return_document_id',
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
        'received_at' => 'datetime',
        'return_requested_at' => 'datetime',
        'required_by_date' => 'date',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function returnDocument()
    {
        return $this->belongsTo(Document::class, 'return_document_id');
    }

    /**
     * Eloquent Boot Handler: Automatically generates an
     * incremental sequential request number on creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (! $model->request_number) {
                $model->request_number = app(RequestNumberService::class)->generate();
            }
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
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    /**
     * Define the relationship to the Snipe-IT user who approved this Service Request.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }
}
