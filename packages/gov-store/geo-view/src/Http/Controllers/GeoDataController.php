<?php

namespace GovStore\GeoView\Http\Controllers;

use GovStore\GeoView\Contracts\GeoDataSource;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Registry\GeoSourceRegistry;
use GovStore\GeoView\Services\GeoDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * gs-geo-data/1 and details rows (plan §6.5). Every request is re-authorized and re-scoped here; the config a
 * page sent to the browser is never trusted. Missing base ability → 403; unknown source or failed object check
 * → 404 with a body that names nothing (tracking rule 2); bad params → 422.
 */
class GeoDataController extends Controller
{
    public function __construct(private GeoSourceRegistry $sources, private GeoDataService $service) {}

    public function data(Request $request, string $source): JsonResponse
    {
        $started = microtime(true);
        [$src, $geo] = $this->prepare($request, $source);
        $response = $this->json($request, $this->service->payload($src, $geo));
        $ms = (microtime(true) - $started) * 1000;
        if ($ms > (int) config('geo-view.slow_ms', 500)) {
            Log::info('geo-view: slow data request', ['source' => $source, 'ms' => (int) $ms]);
        }

        return $response;
    }

    public function details(Request $request, string $source): JsonResponse
    {
        [$src, $geo] = $this->prepare($request, $source, true);

        return $this->json($request, $this->service->details($src, $geo, (int) $geo->area));
    }

    /** @return array{0: GeoDataSource, 1: GeoRequest} */
    private function prepare(Request $request, string $key, bool $needsArea = false): array
    {
        $source = preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $key) ? $this->sources->get($key) : null;
        abort_unless($source, 404);
        abort_unless($request->user() && Gate::forUser($request->user())->allows($source->ability()), 403);

        $common = [
            'area' => [$needsArea ? 'required' : 'nullable', 'regex:/^(auto|\d{1,9})$/'],
            'layer' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]{0,40}$/'],
            'fallback' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9_,]*$/'],
            'filters' => ['nullable', 'array', 'max:10'],
            'filters.*' => ['nullable', 'string', 'max:60'],
            'urban' => ['nullable', 'in:colour,hide,drill'],
            'level' => ['nullable', 'integer', 'in:2,3'],
        ];
        $validator = Validator::make($request->query(), $common + $source->rules());
        $validated = $validator->validate();   // 422 on tampered params
        $params = array_intersect_key($validated, $source->rules());
        $geo = new GeoRequest(
            user: $request->user(),
            params: $params,
            area: ($validated['area'] ?? 'auto') === 'auto' ? 'auto' : (int) $validated['area'],
            layer: $validated['layer'] ?? null,
            fallback: array_values(array_filter(explode(',', (string) ($validated['fallback'] ?? '')))),
            filters: array_filter((array) ($validated['filters'] ?? []), fn ($v) => $v !== null && $v !== ''),
            locale: app()->getLocale(),
            urban: $validated['urban'] ?? 'colour',
            level: isset($validated['level']) ? (int) $validated['level'] : null,
        );
        // Unknown filter values are rejected like any other tampered input.
        foreach ($source->filters($geo) as $filter) {
            $value = $geo->filter($filter->key);
            if ($value !== null && ! $filter->allows($value)) {
                abort(422);
            }
        }
        abort_unless($source->authorize($geo), 404);

        return [$source, $geo];
    }

    private function json(Request $request, array $body): JsonResponse
    {
        $response = response()->json($body, 200, [], JSON_UNESCAPED_UNICODE);
        $etag = '"'.sha1((string) $response->getContent()).'"';
        $response->headers->set('ETag', $etag);
        // Scoped data: browser revalidation only, never a shared cache.
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->headers->set('Vary', 'Cookie, Accept-Language');
        if ($request->headers->get('If-None-Match') === $etag) {
            $response->setNotModified();
        }

        return $response;
    }
}
