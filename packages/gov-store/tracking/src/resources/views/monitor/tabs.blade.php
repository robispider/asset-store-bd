<nav class="gs-level-switch" aria-label="{{ __('govtracking::general.monitor.programme') }}">
    <a class="gs-level-switch__option" href="{{ route('gov.tracking.initiatives.show',$initiative->id) }}" @if(request()->routeIs('gov.tracking.initiatives.show')) aria-current="page" @endif>{{ __('govtracking::general.monitor.monitor') }}</a>
    <a class="gs-level-switch__option" href="{{ route('gov.tracking.initiatives.manage',$initiative->id) }}" @if(request()->routeIs('gov.tracking.initiatives.manage')) aria-current="page" @endif>{{ __('govtracking::general.monitor.manage') }}</a>
    <a class="gs-level-switch__option" href="{{ route('gov.tracking.initiatives.report',$initiative->id) }}">{{ __('govtracking::general.monitor.report') }}</a>
    @if(config('tracking.office_directory_enabled'))<a class="gs-level-switch__option" href="{{ route('gov.tracking.offices.index') }}">{{ __('govtracking::general.monitor.offices') }}</a>@endif
</nav>
