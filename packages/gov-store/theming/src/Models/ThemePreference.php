<?php

namespace GovStore\Theming\Models;

use Illuminate\Database\Eloquent\Model;

class ThemePreference extends Model
{
    public const MODES = ['light', 'dark', 'system'];

    protected $table = 'gs_theme_preferences';

    protected $fillable = ['user_id', 'theme', 'mode'];

    protected $attributes = ['mode' => 'system'];
}
