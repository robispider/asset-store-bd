@php
    $months=[];
    foreach(collect($code['receipts'])->filter(fn($r)=>$r['date'])->groupBy(fn($r)=>substr($r['date'],0,7))->sortKeys() as $month=>$records) $months[$month]=$records->pluck('grn')->filter()->unique()->count();
    $values=array_values($months); $points=[]; $max=max([1,...$values]);
    foreach($values as $index=>$value) $points[]=(count($values)===1 ? 50 : round(2+96*$index/(count($values)-1),2)).','.round(28-24*$value/$max,2);
@endphp
@if($values && !$code['unknown_count'])<details class="gs-tracking-spark"><summary><svg viewBox="0 0 100 32" role="img" aria-label="{{ __('govtracking::general.monitor.trend') }}">@if(count($values)===1)<circle cx="50" cy="{{ explode(',',$points[0])[1] }}" r="3" />@else<polyline points="{{ implode(' ',$points) }}" />@endif<title>{{ implode(', ',$values) }}</title></svg>@if(count($values)===1)<span>{{ trans_choice('govtracking::general.monitor.receipt_count_short',$values[0]) }} · {{ array_key_first($months) }}</span>@endif</summary><table><thead><tr><th>{{ __('govtracking::general.monitor.date') }}</th><th>{{ __('govtracking::general.monitor.receipts') }}</th></tr></thead><tbody>@foreach($months as $month=>$count)<tr><td>{{ $month }}</td><td>{{ $count }}</td></tr>@endforeach</tbody></table></details>@else<span>—</span>@endif
