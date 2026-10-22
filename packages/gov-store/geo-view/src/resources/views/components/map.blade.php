{{-- <x-geoview::map>: x-gs::geo-map with the resolved config, the first payload and the server-rendered table. --}}
@php
    $geoRows = [];
    $geoDigits = fn ($v) => str_starts_with(app()->getLocale(), 'bn') ? strtr((string) $v, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']) : (string) $v;
    $geoLayer = $payload['layer'] ?? null;
    foreach (($payload['areas'] ?? []) as $geoId => $geoArea) {
        $geoValue = null;
        if (in_array($geoArea['state'], ['ok', 'partial'], true) && ($geoArea['value'] ?? null) !== null) {
            $geoValue = $geoArea['display'] ?? $geoDigits(match ($geoLayer['format'] ?? 'integer') {
                'percent' => number_format($geoArea['value'] * 100, 1).'%',
                'decimal' => number_format($geoArea['value'], 2),
                default => number_format($geoArea['value']),
            });
        }
        $geoRows[] = ['id' => $geoId, 'name' => $geoArea['name'], 'value' => $geoValue, 'offices' => $geoArea['offices'] ?? null, 'state' => $geoArea['state']];
    }
    usort($geoRows, fn ($a, $b) => strcmp($a['name'], $b['name']));
@endphp
<x-gs::geo-map :config="$config" :payload="$payload" :label="$label" {{ $attributes }}>
    @if ($payload)
        <table class="gs-geo__table">
            <caption>{{ $payload['layer']['label'] }} · {{ $payload['area']['name'] }}</caption>
            <thead>
                <tr>
                    <th scope="col">{{ __('gs-theme::kit.geo.area') }}</th>
                    <th scope="col" class="gs-geo__num">{{ $payload['layer']['label'] }}</th>
                    <th scope="col" class="gs-geo__num">{{ __('gs-theme::kit.geo.offices') }}</th>
                    <th scope="col">{{ __('gs-theme::kit.geo.state') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($geoRows as $geoRow)
                    <tr>
                        <td>{{ $geoRow['name'] }}</td>
                        <td class="gs-geo__num">{{ $geoRow['value'] ?? '—' }}</td>
                        <td class="gs-geo__num">{{ $geoRow['offices'] ? $geoDigits($geoRow['offices']) : '—' }}</td>
                        <td>{{ $geoRow['state'] === 'ok' ? '' : __('gs-theme::kit.geo.'.$geoRow['state']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if (($payload['here']['offices'] ?? 0) > 0)
            <p>{{ __('gs-theme::kit.geo.here') }}: {{ $geoDigits($payload['here']['offices']) }}</p>
        @endif
    @else
        <p>{{ __('gs-theme::kit.geo.denied') }}</p>
    @endif
    {{ $slot ?? '' }}
</x-gs::geo-map>
