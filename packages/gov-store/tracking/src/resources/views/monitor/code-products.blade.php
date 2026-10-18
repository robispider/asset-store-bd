<div class="gs-tracking-product-detail">
    <div class="gs-tracking-product-detail__heading">
        <strong>{{ __('govtracking::general.monitor.product_breakdown') }} · {{ __('govtracking::general.monitor.received_planned') }}</strong>
        <span>{{ __('govtracking::general.monitor.lowest_delivery_first') }} · <a href="{{ route('gov.tracking.initiatives.tracking-codes.show',[$initiative->id,$code['id']]) }}">{{ __('govtracking::general.monitor.code_details') }} →</a></span>
    </div>
    @if($code['status']==='DRAFT')<p class="gs-tracking-muted">{{ __('govtracking::general.monitor.draft_receipts') }}</p>@endif
    <ul class="gs-tracking-product-grid">
        @forelse(collect($code['targets'])->sortBy(fn($target)=>$target['fulfilment'] ?? PHP_FLOAT_MAX) as $target)
            @php($share=$code['status']==='ACTIVE' && $target['fulfilment']!==null ? max(0,min(100,$target['fulfilment']*100)) : null)
            <li class="gs-tracking-product">
                <span class="gs-tracking-product__name">{{ $target['name'] }}</span>
                <span class="gs-tracking-product__quantity">{{ number_format($target['received']) }} / {{ $target['planned']>0 ? number_format($target['planned']) : '—' }}</span>
                @if($share!==null)<span class="gs-tracking-product__bar" role="progressbar" aria-label="{{ $target['name'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $share }}"><span style="width:{{ $share }}%"></span></span>@endif
            </li>
        @empty
            <li class="gs-tracking-muted">{{ __('govtracking::general.monitor.no_items') }}</li>
        @endforelse
    </ul>
</div>
