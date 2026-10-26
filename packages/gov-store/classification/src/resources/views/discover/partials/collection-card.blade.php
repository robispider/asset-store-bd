<li class="gs-discover-card">
    <span class="gs-discover-card__icon" aria-hidden="true"><i class="{{ $collection->icon ?: 'fas fa-layer-group' }}"></i></span>
    <div class="gs-discover-card__body">
        <div>
            <h3 class="gs-discover-card__title">{{ $collection->display_name }}</h3>
            <p class="gs-discover-card__meta">{{ __('classification::collections.count', ['count' => $collection->nodes_count]) }}</p>
        </div>
        <a href="{{ route('gov.catalog.discover.collections.show', $collection->id) }}" class="gs-discover-link">
            {{ __('classification::collections.explore') }} <i class="fas fa-arrow-right" aria-hidden="true"></i>
            <span class="gs-sr-only">: {{ $collection->display_name }}</span>
        </a>
    </div>
</li>
