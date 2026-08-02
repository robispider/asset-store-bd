<?php

namespace GovStore\Tracking\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationUnit extends Model
{
    protected $table = 'gov_tracking_operation_units';

    // Declaring explicit role constants for programmatic security gates
    const DESIGNATION_HEAD = 'HEAD';
    const DESIGNATION_OFFICER = 'OFFICER';
    const DESIGNATION_SUPPORT = 'SUPPORT';
    const DESIGNATION_MONITOR = 'MONITOR';

    protected $fillable = [
        'initiative_id',
        'user_id',
        'designation', // HEAD, OFFICER, SUPPORT, MONITOR
    ];

    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class, 'initiative_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}