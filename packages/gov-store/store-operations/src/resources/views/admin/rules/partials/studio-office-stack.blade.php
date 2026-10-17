{{-- View C: one ministry's offices, stacked. Past six (plus the chosen one) the rest fold away. --}}
@php
    $shown = collect($offices)->filter(fn ($office, $i) => $i < 6 || $office['id'] === $chosenOffice)->values();
    $folded = collect($offices)->reject(fn ($office, $i) => $i < 6 || $office['id'] === $chosenOffice)->values();
@endphp
<ul class="rst-oc-stack">
    @foreach ($shown as $office)
        <li>
            @include('storeops::admin.rules.partials.studio-card', ['n' => $office, 'level' => $layerName('LOCATION'),
                'href' => $studioUrl(['location' => $office['id'], 'company' => null, 'node' => 'location:'.$office['id']]),
                'current' => $chosenOffice === $office['id'], 'path' => $chosenOffice === $office['id']])
        </li>
    @endforeach
    @if ($folded->isNotEmpty())
        <li>
            <details class="rst-fold">
                <summary>{{ trans_choice('storeops::studio.more_offices', $folded->count(), ['count' => $folded->count()]) }}</summary>
                <ul class="rst-oc-stack">
                    @foreach ($folded as $office)
                        <li>
                            @include('storeops::admin.rules.partials.studio-card', ['n' => $office, 'level' => $layerName('LOCATION'),
                                'href' => $studioUrl(['location' => $office['id'], 'company' => null, 'node' => 'location:'.$office['id']]),
                                'current' => false, 'path' => false])
                        </li>
                    @endforeach
                </ul>
            </details>
        </li>
    @endif
</ul>
