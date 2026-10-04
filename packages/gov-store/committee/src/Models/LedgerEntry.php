<?php

namespace GovStore\Committee\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $table = 'gov_committee_ledger';
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'array', 'actor_roles' => 'array'];

    public $timestamps = false;
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new \LogicException('Ledger entries are append-only.'));
    }

}

