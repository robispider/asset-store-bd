<?php

namespace GovStore\OfficeMembership\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class EmployeeVerificationToken extends Model
{
    protected $table = 'gov_employee_verification_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Get the user that generated this token.
     * Uses targeted scope bypass to ensure identity resolution succeeds.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withoutGlobalScope(\GovStore\TenantScope\Scopes\UserScope::class);
    }

    /**
     * Check if the token is valid (not expired and not used).
     */
    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }
}