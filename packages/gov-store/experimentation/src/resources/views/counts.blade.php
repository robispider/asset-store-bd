@php
    $labels = __('experiments::ui.record_names');
    $other = array_sum(array_diff_key($counts, $labels));
@endphp
<div class="box"><div class="box-header"><h2 class="box-title">{{ __('experiments::ui.counts') }}</h2></div><div class="box-body table-responsive"><table class="table table-striped"><thead><tr><th>{{ __('experiments::ui.record_type') }}</th><th>{{ __('experiments::ui.count') }}</th></tr></thead><tbody>@foreach ($counts as $table => $count)@if (isset($labels[$table]))<tr><td>{{ $labels[$table] }}</td><td>{{ number_format($count) }}</td></tr>@endif @endforeach
@if ($other)<tr><td>{{ __('experiments::ui.support_records') }}</td><td>{{ number_format($other) }}</td></tr>@endif
</tbody><tfoot><tr><th>{{ __('experiments::ui.total') }}</th><th>{{ number_format(array_sum($counts)) }}</th></tr></tfoot></table></div></div>
