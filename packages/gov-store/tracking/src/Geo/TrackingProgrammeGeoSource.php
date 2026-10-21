<?php

namespace GovStore\Tracking\Geo;

use GovStore\GeoView\Contracts\CachesFacts;
use GovStore\GeoView\Contracts\GeoDataSource;
use GovStore\GeoView\Data\FilterDefinition;
use GovStore\GeoView\Data\GeoRequest;
use GovStore\GeoView\Data\GeoScope;
use GovStore\GeoView\Data\LayerDefinition;
use GovStore\GeoView\Data\OfficeFact;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Services\TrackingAuthorizationService;
use GovStore\Tracking\Services\TrackingMonitorMetrics;
use GovStore\Tracking\Services\TrackingMonitorService;

/**
 * Programme delivery by office for the geo view (geo-view plan phase E). Office figures come from the
 * programme monitor itself (TrackingMonitorService::programme → officeSummary), so the map shows exactly the
 * tracking plan §5 metrics the page shows: office share = done / allocated, received quantity, value and
 * waiting offices. Scope is the programme key: every office of the programme (tracking plan §6).
 */
class TrackingProgrammeGeoSource implements GeoDataSource, CachesFacts
{
    /** @var array<string, array> monitor results per (initiative, filters) within one request */
    private array $monitors = [];

    public function __construct(private TrackingMonitorService $monitor, private TrackingAuthorizationService $authorization) {}

    public function key(): string
    {
        return 'tracking.programme';
    }

    public function label(): string
    {
        return 'govtracking::general.geo.source';
    }

    public function ability(): string
    {
        return 'tracking.programmes.view';
    }

    public function rules(): array
    {
        return [
            'initiative' => ['required', 'integer', 'min:1'],
            'code' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:all,ACTIVE,DRAFT'],
            'plan' => ['nullable', 'in:all,1_BLANKET,2_CATEGORY,3_MATRIX'],
            'q' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function authorize(GeoRequest $request): bool
    {
        // InitiativeScope hides programmes the viewer may not know about; the programme key decides the rest.
        $initiative = $this->initiative($request);

        return $initiative !== null && $this->authorization->allows($initiative);
    }

    public function scope(GeoRequest $request): GeoScope
    {
        return GeoScope::offices(array_column($this->programme($request)['offices'], 'id'));
    }

    public function grain(): string
    {
        return self::GRAIN_OFFICE;
    }

    public function layers(GeoRequest $request): array
    {
        return [
            new LayerDefinition('delivered_share', __('govtracking::general.geo.delivered_share'), 'percent', 'ratio',
                notApplicableNote: __('govtracking::general.geo.no_office_targets')),
            new LayerDefinition('received_qty', __('govtracking::general.geo.received_qty'), 'integer', 'sum', __('govtracking::general.geo.items'),
                display: fn ($v) => TrackingMonitorMetrics::digits(number_format($v))),
            new LayerDefinition('value', __('govtracking::general.geo.value'), 'money', 'sum',
                display: fn ($v) => TrackingMonitorMetrics::money((float) $v)),
            new LayerDefinition('waiting_offices', __('govtracking::general.geo.waiting_offices'), 'integer', 'count'),
        ];
    }

    public function filters(GeoRequest $request): array
    {
        $codes = $this->programme($request)['codes'];

        return [new FilterDefinition('code', __('govtracking::general.monitor.code'), array_map(
            fn ($c) => ['value' => $c['id'], 'label' => $c['code']], $codes,
        ))];
    }

    public function facts(GeoRequest $request, array $layers): iterable
    {
        $initiative = (int) $request->param('initiative');
        $code = $request->filter('code') ?? $request->param('code');
        $bangla = str_starts_with($request->locale(), 'bn');
        foreach ($this->programme($request)['offices'] as $office) {
            $summary = $office;
            if ($code !== null && $code !== '') {
                $slices = array_values(array_filter($office['codes'], fn ($s) => (int) $s['code']['id'] === (int) $code));
                if (! $slices) {
                    continue;
                }
                $summary = $this->monitor->officeSummary($slices) + $office;
            }
            yield new OfficeFact(
                (int) $office['id'],
                [
                    'delivered_share' => [(float) $summary['done'], (float) $summary['allocated']],
                    'received_qty' => (float) $summary['received'],
                    'value' => $summary['value'] === null ? null : (float) $summary['value'],
                    'waiting_offices' => $summary['allocated'] > 0 && $summary['done'] == 0 ? 1 : 0,
                ],
                $bangla && ($office['name_bn'] ?? '') !== '' ? $office['name_bn'] : $office['name'],
                route('gov.tracking.initiatives.offices.show', [$initiative, $office['id']], false),
                isset($office['profile']->geo_area_id) && $office['profile']->geo_area_id ? (int) $office['profile']->geo_area_id : null,
            );
        }
    }

    public function details(GeoRequest $request, int $areaId): ?array
    {
        return null;
    }

    public function factsTtl(): int
    {
        return (int) config('tracking.geo_facts_ttl', 60);
    }

    private function initiative(GeoRequest $request): ?Initiative
    {
        return Initiative::query()->find((int) $request->param('initiative'));
    }

    /** The programme monitor for this request (status/plan/q filters as on the page), computed once. */
    private function programme(GeoRequest $request): array
    {
        $filters = array_filter(['status' => $request->param('status'), 'plan' => $request->param('plan'), 'q' => $request->param('q')], fn ($v) => $v !== null && $v !== '');
        $key = (int) $request->param('initiative').':'.json_encode($filters).':'.$request->locale();
        if (! isset($this->monitors[$key])) {
            $initiative = $this->initiative($request);
            abort_unless($initiative, 404);
            $this->monitors[$key] = $this->monitor->programme($initiative, $filters);
        }

        return $this->monitors[$key];
    }
}
