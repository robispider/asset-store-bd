@props(['items' => [], 'columns' => 2])
{{-- --gs-kv-columns is a layout count, not a colour (R3 allows it). --}}
<dl {{ $attributes->class('gs-kv') }} style="--gs-kv-columns: {{ max(1, min(3, (int) $columns)) }}">
    @foreach ($items as $item)
        <div class="gs-kv__item">
            <dt class="gs-kv__label">{{ $item['label'] }}</dt>
            <dd class="gs-kv__value">{{ $item['value'] }}</dd>
        </div>
    @endforeach
</dl>
