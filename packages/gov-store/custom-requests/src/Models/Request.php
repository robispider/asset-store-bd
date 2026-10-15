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
        'submission_batch',
        'resolved_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'received_at' => 'datetime',
        'return_requested_at' => 'datetime',
        'required_by_date' => 'date',
        'approved_at' => 'datetime',
        'closed_at' => 'datetime',
        'resolved_reason' => 'array',
    ];

    /**
     * One status for the whole request, derived from the approval and fulfilment columns, receipt and
     * return (plan P0, board 2). Every combination maps to exactly one theming status-map key, including
     * the stored historical approval_status "closed" (mapped by its fulfilment) and "cannot_fulfill".
     */
    public function statusKey(): string
    {
        $approval = $this->approval_status;
        $fulfilment = $this->fulfillment_status;

        return match (true) {
            (bool) $this->return_document_id => 'request_return_drafted',
            (bool) $this->return_requested_at => 'request_return_requested',
            $approval === 'rejected' => 'request_rejected',
            $approval === 'cancelled' => 'request_withdrawn',
            (bool) $this->received_at => 'request_received',
            $fulfilment === 'issued' => 'request_issued',
            in_array($fulfilment, ['closed', 'cannot_fulfill'], true) => 'request_closed_short',
            ! $this->office_id => 'request_needs_review',
            $approval === 'pending_final' => 'request_pending_final',
            in_array($approval, ['pending_primary', 'draft', 'submitted', 'under_review'], true) => 'request_pending_primary',
            $fulfilment === 'partially_issued' => 'request_partly_issued',
            default => 'request_approved',
        };
    }

    /** Which My requests tab the request belongs to: needs_you (confirm receipt), active or finished. */
    public function tabKey(): string
    {
        return match ($this->statusKey()) {
            'request_issued' => 'needs_you',
            'request_received', 'request_closed_short', 'request_rejected', 'request_withdrawn' => 'finished',
            default => 'active',
        };
    }

    /** Requests sent together from the same draft (D2), excluding this one. */
    public function siblings()
    {
        return $this->hasMany(self::class, 'submission_batch', 'submission_batch')
            ->whereNotNull('submission_batch')->where('id', '!=', $this->id ?? 0);
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by')->withTrashed();
    }

    public function primaryDecider()
    {
        return $this->belongsTo(User::class, 'primary_decided_by')->withTrashed();
    }

    public function assignedApprover()
    {
        return $this->belongsTo(User::class, 'assigned_approver_id')->withTrashed();
    }

    public function office()
    {
        return $this->belongsTo(\App\Models\Location::class, 'office_id')->withTrashed();
    }

    public function deliveryLocation()
    {
        return $this->belongsTo(\App\Models\Location::class, 'delivery_location_id')->withTrashed();
    }

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
