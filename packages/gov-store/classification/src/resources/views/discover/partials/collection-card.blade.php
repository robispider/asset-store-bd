@php
    $remaining = count($state['remaining']);
    $total = $collection->nodes_count;
@endphp
<li class="gs-discover-card">
    <span class="gs-discover-card__icon" aria-hidden="true"><i class="{{ $collection->icon ?: 'fas fa-layer-group' }}"></i></span>
    <div class="gs-discover-card__body">
        <div>
            <h3 class="gs-discover-card__title">{{ $collection->display_name }}</h3>
            <p class="gs-discover-card__meta">
                {{ __('classification::collections.count', ['count' => $total]) }}
                @if ($total > 0)
                    · {{ __('classification::discover.collections.card_progress', ['adopted' => $state['adopted'], 'total' => $total]) }}
                @endif
            </p>
        </div>
        @if ($total > 0)
            <div class="gs-discover-bars__track" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $state['adopted'] }}"
                aria-label="{{ __('classification::collections.progress') }}: {{ $collection->display_name }}"><span class="gs-discover-bars__bar gs-discover-bars__bar--done" style="width: {{ round($state['adopted'] / $total * 100) }}%"></span></div>
        @endif
        <div class="gs-discover-card__actions">
            @if ($remaining > 0 && ($canAdopt ?? false))
                <button type="button" class="gs-btn gs-btn--primary gs-btn--outline gs-btn--sm" data-adopt-collection="{{ $collection->id }}">
                    <i class="fas fa-check-double" aria-hidden="true"></i> {{ __('classification::discover.collections.adopt_all', ['count' => $remaining]) }}
                    <span class="gs-sr-only">: {{ $collection->display_name }}</span>
                </button>
            @elseif ($total > 0 && $remaining === 0)
                <x-gs::badge tone="success" pill><i class="fas fa-check" aria-hidden="true"></i> {{ __('classification::collections.complete') }}</x-gs::badge>
            @endif
            <a href="{{ route('gov.catalog.discover.collections.show', $collection->id) }}" class="gs-discover-link">
                {{ __('classification::collections.explore') }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
                <span class="gs-sr-only">: {{ $collection->display_name }}</span>
            </a>
        </div>
    </div>
</li>
