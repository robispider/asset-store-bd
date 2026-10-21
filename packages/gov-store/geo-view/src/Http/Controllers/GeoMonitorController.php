<?php

namespace GovStore\GeoView\Http\Controllers;

use GovStore\GeoView\Registry\GeoPresetRegistry;
use GovStore\GeoView\Registry\GeoSourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * The national geo monitor (geo-view plan phase F): one host page for every preset marked `national`.
 * The page itself shows no figures; each map asks its source, which re-authorizes and scopes on the server.
 */
class GeoMonitorController extends Controller
{
    public function index(Request $request, GeoPresetRegistry $presets, GeoSourceRegistry $sources)
    {
        $user = $request->user();
        $available = [];
        foreach ($presets->all() as $key => $preset) {
            $source = $sources->get($preset['source']['key'] ?? '');
            if (($preset['national'] ?? false) && $source && Gate::forUser($user)->allows($source->ability())) {
                $available[$key] = __($preset['title'] ?? $source->label());
            }
        }
        $current = $request->query('map');
        if (! is_string($current) || ! isset($available[$current])) {
            $current = array_key_first($available);
        }

        return view('geoview::monitor', ['available' => $available, 'current' => $current]);
    }
}
