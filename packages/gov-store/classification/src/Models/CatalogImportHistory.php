<?php

namespace GovStore\Classification\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogImportHistory extends Model
{
    protected $table = 'gov_catalog_import_history';
    protected $guarded = ['id'];

    protected $casts = [
        'rows_processed'   => 'integer',
        'warnings'         => 'integer',
        'duration_seconds' => 'float',
        'user_id'          => 'integer',
        'imported_at'      => 'datetime',
    ];

    /**
     * Get the user who performed the import.
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Scope to a specific scheme.
     */
    public function scopeByScheme($query, string $scheme)
    {
        return $query->where('scheme', $scheme);
    }

    /**
     * Scope to imports with warnings.
     */
    public function scopeWithWarnings($query)
    {
        return $query->where('warnings', '>', 0);
    }

    /**
     * Get a human-readable duration string.
     */
    public function getDurationAttribute(): string
    {
        $seconds = (int) $this->duration_seconds;
        if ($seconds < 60) {
            return "{$seconds}s";
        }
        $minutes = intdiv($seconds, 60);
        $remainingSeconds = $seconds % 60;
        return "{$minutes}m {$remainingSeconds}s";
    }
}
