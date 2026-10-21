<div class="gs-portfolio-actions">
    <a class="gs-btn gs-btn--primary gs-btn--sm" href="{{ route('gov.tracking.initiatives.show',$p['id']) }}">{{ __('govtracking::general.monitor.monitor') }}</a>
    @if($p['can_manage'])<a class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" href="{{ route('gov.tracking.initiatives.manage',$p['id']) }}">{{ __('govtracking::general.monitor.manage') }}</a>@endif
    <a class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" href="{{ route('gov.tracking.initiatives.report',$p['id']) }}">{{ __('govtracking::general.monitor.report') }}</a>
    @if($p['can_edit'])<a class="gs-btn gs-btn--secondary gs-btn--outline gs-btn--sm" href="{{ route('gov.tracking.initiatives.edit',$p['id']) }}">{{ __('govtracking::portfolio.properties') }}</a>@endif
</div>
