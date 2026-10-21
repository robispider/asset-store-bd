<span class="gs-portfolio-money">{{ $money($p['value']) }}</span>
@if(!$p['value_complete'])<small class="gs-tracking-block gs-portfolio-incomplete">{{ __('govtracking::portfolio.incomplete') }}</small><small class="gs-tracking-block gs-tracking-muted">{{ __('govtracking::general.monitor.known_value') }}: {{ $money($p['known_value']) }}</small>@endif
