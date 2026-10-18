<x-gs::table class="gs-tracking-receipts"><thead><tr>
    @foreach(['date','receipt','code','programme','office','item','received','unit_price','value','supplier','manufacturer'] as $heading)<th>{{ __('govtracking::general.monitor.'.$heading) }}</th>@endforeach
</tr></thead><tbody>
@forelse(collect($receipts)->sortByDesc('date') as $receipt)<tr>
    <td>{{ $receipt['date'] ? \Carbon\CarbonImmutable::parse($receipt['date'])->toDateString() : __('govtracking::general.monitor.unavailable') }}</td>
    <td>{{ $receipt['grn'] ?? __('govtracking::general.monitor.unavailable') }}</td><td>{{ $receipt['code'] ?? '—' }}</td><td>{{ $receipt['programme'] ?? '—' }}</td><td>{{ $receipt['office'] }}</td><td>{{ $receipt['item'] }}</td>
    <td>{{ number_format($receipt['quantity']) }}</td><td>{{ \GovStore\Tracking\Services\TrackingMonitorMetrics::money($receipt['value']!==null && $receipt['quantity']>0 ? $receipt['value']/$receipt['quantity'] : null) }}</td>
    <td>{{ \GovStore\Tracking\Services\TrackingMonitorMetrics::money($receipt['value']) }}</td><td>{{ $receipt['supplier'] ?? '—' }}</td><td>{{ $receipt['manufacturer'] ?? '—' }}</td>
</tr>@empty<tr><td colspan="11">{{ __('govtracking::general.monitor.no_receipts') }}</td></tr>@endforelse
</tbody></x-gs::table>
