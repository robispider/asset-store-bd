<?php

namespace GovStore\Theming\Compiler;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

/**
 * Attaches the theme to Snipe-IT's layouts when Blade compiles them (once; cached in
 * storage/framework/views). Core files are never edited. A missing anchor skips that
 * insertion, so the page falls back to stock Snipe-IT styling (and the contract test fails).
 */
final class LayoutHook
{
    public const VERSION = 'gs-theme-hook:v1';

    public const TARGETS = [
        'resources/views/layouts/default.blade.php',
        'resources/views/layouts/basic.blade.php',
        'resources/views/layouts/setup.blade.php',
    ];

    public const HTML_TAG = '/<html\b(?:\{\{.*?\}\}|\{!!.*?!!\}|"[^"]*"|\'[^\']*\'|[^>"\'])*>/is';

    public const CUSTOM_CSS_ANCHOR = '/@if\s*\(\s*\(\s*\$snipeSettings\s*\)\s*&&\s*\(\s*\$snipeSettings->custom_css\s*\)\s*\)/';

    public function __invoke(string $source): string
    {
        $path = str_replace('\\', '/', (string) Blade::getPath());
        if (! Str::endsWith($path, self::TARGETS) || str_contains($source, self::VERSION)) {
            return $source;
        }

        return self::apply($source);
    }

    public static function apply(string $source): string
    {
        // 1. <html …>: server-resolved skin, mode and variants (replaces core's hard-coded data-theme).
        // Quoted values and Blade echoes are consumed whole: they contain `->`, which a naive [^>]* stops at.
        $source = preg_replace_callback(self::HTML_TAG, function ($m) {
            $tag = preg_replace('/\sdata-theme="[^"]*"/', '', $m[0]);

            return Str::replaceLast('>', ' {!! app(\'gs.theme\')->htmlAttributes() !!}>', $tag);
        }, $source, 1);

        // 2. Head assets: before the admin Custom CSS block (Custom CSS stays the final word), else before </head>.
        $head = "@include('gs-theme::head')\n";
        if (preg_match(self::CUSTOM_CSS_ANCHOR, $source)) {
            $source = preg_replace(self::CUSTOM_CSS_ANCHOR, addcslashes($head, '\\$').'$0', $source, 1);
        } elseif (str_contains($source, '</head>')) {
            $source = Str::replaceLast('</head>', $head.'</head>', $source);
        }

        // 3. Runtime helpers before the last </body>.
        if (str_contains($source, '</body>')) {
            $source = Str::replaceLast('</body>', "@include('gs-theme::foot')\n</body>", $source);
        }

        // A PHP comment (unlike a Blade comment) survives compilation, so compiled views can be checked.
        return '<?php /* '.self::VERSION.' */ ?>'.$source;
    }
}
