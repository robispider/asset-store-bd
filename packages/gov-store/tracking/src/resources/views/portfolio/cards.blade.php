<div class="gs-portfolio-grid">@foreach($programmes as $p)
    <article class="gs-portfolio-card" data-portfolio-programme="{{ $p['id'] }}" data-state="{{ $p['status'] }}">
        <div class="gs-portfolio-card-top">@include('govtracking::portfolio.health')<span class="gs-portfolio-chip">{{ $p['funding'] }}</span>@if($p['mine'])<span class="gs-portfolio-chip">{{ __('govtracking::portfolio.your_team') }}</span>@endif</div>
        <div><h2><a href="{{ route('gov.tracking.initiatives.show',$p['id']) }}">{{ $p['title'] }}</a></h2><p class="gs-tracking-muted">{{ $p['owner'] }}</p><small class="gs-tracking-muted">{{ implode(' · ',$p['years']) }}</small></div>
        @if($p['status']==='Planning')
            <x-gs::readiness :items="$p['readiness']" readonly />
        @else
            <div><span class="gs-portfolio-label">{{ __('govtracking::general.monitor.delivered') }}</span>@include('govtracking::portfolio.pace')</div>
            <dl class="gs-portfolio-card-stats"><div><dt>{{ __('govtracking::general.monitor.value') }}</dt><dd>@include('govtracking::portfolio.value')</dd></div><div><dt>{{ __('govtracking::portfolio.offices') }}</dt><dd>@include('govtracking::portfolio.offices')</dd></div><div><dt>{{ __('govtracking::general.monitor.codes') }}</dt><dd>{{ $p['active_count'] }} · {{ $p['draft_count'] }}<small class="gs-tracking-block gs-tracking-muted">{{ __('govtracking::general.monitor.active_draft') }}</small></dd></div></dl>
            @if($p['status']==='Active')<div class="gs-portfolio-card-issue">@if($p['attention'])<strong>{{ $p['attention'][0]['title'] }}</strong><p>{{ $p['attention'][0]['detail'] }}</p><small>{{ trans_choice('govtracking::portfolio.issue_count',count($p['attention'])) }}</small>@else{{ __('govtracking::portfolio.'.($p['health']==='no_target' ? 'insufficient_evidence' : 'no_issues')) }}@endif</div>@endif
        @endif
        <div class="gs-portfolio-card-contact"><strong>{{ $p['head']['name'] ?? __('govtracking::portfolio.no_head') }}</strong>@if($p['head'])<small>{{ __('govtracking::general.monitor.designations.HEAD') }}</small>@endif @include('govtracking::portfolio.team')</div>
        <p class="gs-tracking-muted">{{ __('govtracking::portfolio.next_step') }}: {{ $p['next'] }}</p>
        @if($p['last'])<small class="gs-tracking-muted">{{ __('govtracking::general.monitor.last_receipt') }}: {{ \Carbon\CarbonImmutable::parse($p['last'])->toDateString() }}</small>@endif
        <footer>@include('govtracking::portfolio.actions')</footer>
    </article>
@endforeach</div>
