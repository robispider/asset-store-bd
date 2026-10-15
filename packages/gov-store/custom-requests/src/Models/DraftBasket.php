<?php

namespace GovStore\CustomRequests\Models;

use App\Models\User;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A person's draft request in one office. Each office keeps its own draft, so items chosen from one
 * store are never sent to another after the person switches office.
 */
class DraftBasket extends Model
{
    protected $table = 'draft_baskets';

    protected $fillable = ['user_id', 'office_id', 'status', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'office_id' => 'integer',
    ];

    /**
     * The user who owns this draft basket.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Items in the draft basket.
     */
    public function items()
    {
        return $this->hasMany(BasketItem::class, 'basket_id');
    }

    /**
     * The person's open draft in an office (the working office by default). A draft saved before drafts
     * had an office still matches until it is next changed, when it is claimed by that office.
     */
    public function scopeOpenFor(Builder $query, int $userId, ?int $office = null): Builder
    {
        $office ??= app(TenantContext::class)->locationId;

        return $query->where('user_id', $userId)->where('status', 'draft')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q->where('office_id', $office)->orWhereNull('office_id'))
            ->orderByRaw('CASE WHEN office_id IS NULL THEN 1 ELSE 0 END');
    }

    /**
     * Get or create the user's draft in an office (the working office by default).
     */
    public static function getOrCreateForUser(int $userId, ?int $office = null): self
    {
        $office ??= app(TenantContext::class)->locationId;

        return DB::transaction(function () use ($userId, $office) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $basket = static::where('user_id', $userId)->where('status', 'draft')
                ->where(fn ($q) => $q->where('office_id', $office)->orWhereNull('office_id'))
                ->orderByRaw('CASE WHEN office_id IS NULL THEN 1 ELSE 0 END')
                ->first();

            if ($basket && $basket->expires_at && $basket->expires_at->isPast()) {
                $basket->items()->delete();
                $basket->delete();
                $basket = null;
            }
            if ($basket && $basket->office_id === null && $office) {
                $basket->update(['office_id' => $office]);
            }
            if (! $basket) {
                $basket = static::create([
                    'user_id' => $userId,
                    'office_id' => $office,
                    'status' => 'draft',
                    'expires_at' => now()->addDays(7),
                ]);
            }

            return $basket;
        }, 3);
    }
}
