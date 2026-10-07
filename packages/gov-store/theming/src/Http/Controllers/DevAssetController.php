<?php

namespace GovStore\Theming\Http\Controllers;

use GovStore\Theming\ThemeManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Local / staging only: compiles theme assets on request, recompiling whenever a theme,
 * adapter, kit or registered package file changes. Designers edit JSON → refresh.
 */
class DevAssetController extends Controller
{
    public function __invoke(Request $request, ThemeManager $manager, string $file)
    {
        abort_unless($manager->devMode(), 404);
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        if (str_starts_with($file, 'fonts/')) {
            $name = basename($file);
            foreach ($manager->fonts()->availableFiles($manager->fonts()->keys()) as $font) {
                if ($font['file'] === $name) {
                    return response()->file($font['path'], ['Content-Type' => 'font/woff2', 'Cache-Control' => 'public, max-age=3600']);
                }
            }
            abort(404);
        }

        if (preg_match('#^previews/([a-z0-9-]+)\.(light|dark)\.png$#', $file, $m)) {
            $path = $manager->repository()->find($m[1])?->previewPath($m[2]);
            abort_unless($path, 404);

            return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=300']);
        }

        $version = $manager->devVersion();
        $etag = '"'.$version.'-'.sha1($file).'"';
        if ($request->header('If-None-Match') === $etag) {
            return response('', 304, ['ETag' => $etag]);
        }
        $compiler = $manager->compiler();
        $compile = function () use ($file, $compiler, $manager) {
            return match (true) {
                $file === 'kit.css' => $compiler->kitCss(),
                $file === 'packages.css' => $compiler->packagesCss(),
                $file === 'gs-theme.js' => $compiler->js(),
                (bool) preg_match('/^theme-([a-z0-9-]+)\.css$/', $file, $m) && $manager->repository()->find($m[1]) !== null
                    => $compiler->themeCss($manager->repository()->find($m[1]), fn (string $font) => $manager->fontUrl($font)),
                default => null,
            };
        };
        $contents = Cache::store('array')->rememberForever('gs-theme-dev:'.$version.':'.$file, $compile);
        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => str_ends_with($file, '.js') ? 'application/javascript; charset=utf-8' : 'text/css; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'ETag' => $etag,
        ]);
    }
}
