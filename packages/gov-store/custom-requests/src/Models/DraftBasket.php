<?php

namespace GovStore\CustomRequests\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DraftBasket extends Model
{
    protected $table = 'draft_baskets';

    protected $fillable = ['user_id', 'status', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
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
     * Get or create a draft basket for the given user.
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return DB::transaction(function () use ($userId) {
            User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $basket = static::where('user_id', $userId)
                ->where('status', 'draft')
                ->first();

            if ($basket && $basket->expires_at && $basket->expires_at->isPast()) {
                $basket->items()->delete();
                $basket->delete();
                $basket = null;
            }
            if (! $basket) {
                $basket = static::create([
                    'user_id' => $userId,
                    'status' => 'draft',
                    'expires_at' => now()->addDays(7),
                ]);
            }

            return $basket;
        }, 3);
    }
}
