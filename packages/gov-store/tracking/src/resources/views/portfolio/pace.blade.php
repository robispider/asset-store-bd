@if($p['status']==='Planning')
    <x-gs::readiness :items="$p['readiness']" compact readonly />
@elseif($p['status']==='Active')
    <x-gs::pace-bar compact :value="$p['delivered']===null ? null : $p['delivered']*100" :expected="$p['expected']===null ? null : $p['expected']*100" />
@else
    <strong>{{ $p['delivered']===null ? __('govtracking::general.monitor.unavailable') : number_format($p['delivered']*100,1).'%' }}</strong><small class="gs-tracking-block gs-tracking-muted">{{ __('govtracking::portfolio.recorded_delivery') }}</small>
@endif
