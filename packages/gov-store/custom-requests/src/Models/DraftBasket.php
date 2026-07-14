<?php

namespace GovStore\CustomRequests\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

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
        $basket = static::where('user_id', $userId)
            ->where('status', 'draft')
            ->first();

        if (!$basket) {
            $basket = static::create([
                'user_id' => $userId,
                'status' => 'draft',
                'expires_at' => now()->addDays(7),
            ]);
        }

        return $basket;
    }
}
