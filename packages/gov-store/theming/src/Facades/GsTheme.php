<?php

namespace GovStore\Theming\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \GovStore\Theming\Assets\AssetRegistry assets()
 * @method static string key()
 * @method static string mode()
 * @method static \GovStore\Theming\Themes\Theme theme()
 * @method static string htmlAttributes()
 *
 * @see \GovStore\Theming\ThemeManager
 */
class GsTheme extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'gs.theme';
    }
}
