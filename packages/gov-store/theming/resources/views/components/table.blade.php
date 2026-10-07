{{--
    columns: [['key' => 'no', 'label' => 'Document', 'numeric' => false], …] with rows: [[…], …]
    or a default slot with <thead>/<tbody>. `table` / `density` override the theme variant locally.
    bootstrap: emit Snipe-IT bootstrap-table data-* attributes for API-driven lists.
--}}
@props(['columns' => null, 'rows' => [], 'table' => null, 'density' => null, 'sticky' => false, 'caption' => null, 'bootstrap' => false, 'id' => null])
<div class="gs-table-wrap {{ $sticky ? 'gs-table-wrap--sticky' : '' }}" @if ($sticky) tabindex="0" @endif>
    <table {{ $attributes->class(['gs-table', 'table' => $bootstrap])->merge(array_filter([
        'id' => $id,
        'data-table' => $table,
        'data-density' => $density,
    ])) }}
        @if ($bootstrap) data-cookie="true" data-pagination="true" data-side-pagination="server" data-search="true" data-show-columns="true" data-show-refresh="true" @endif>
        @if ($caption)<caption>{{ $caption }}</caption>@endif
        @if ($columns)
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col" @class(['gs-num' => $column['numeric'] ?? false]) @if ($bootstrap) data-field="{{ $column['key'] }}" data-sortable="{{ ($column['sortable'] ?? true) ? 'true' : 'false' }}" @endif>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            @unless ($bootstrap)
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            @foreach ($columns as $column)
                                <td @class(['gs-num' => $column['numeric'] ?? false])>{{ $row[$column['key']] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td class="gs-table__empty" colspan="{{ count($columns) }}">{{ $empty ?? __('gs-theme::appearance.kit.empty') }}</td></tr>
                    @endforelse
                </tbody>
            @endunless
        @else
            {{ $slot }}
        @endif
        @isset($totals)<tfoot>{{ $totals }}</tfoot>@endisset
    </table>
</div>
