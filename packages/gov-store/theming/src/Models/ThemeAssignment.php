<?php

namespace GovStore\Theming\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeAssignment extends Model
{
    public const SCOPES = ['organization', 'company', 'office'];

    protected $table = 'gs_theme_assignments';

    protected $fillable = ['scope_type', 'scope_id', 'theme', 'enforced', 'updated_by'];

    protected $casts = ['enforced' => 'boolean', 'scope_id' => 'integer'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withoutGlobalScopes();
    }

    public static function cacheKey(string $scope, ?int $id): string
    {
        return 'gs-theme:assignments:'.$scope.':'.($id ?? 'all');
    }
}
