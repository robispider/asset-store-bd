<div class="box box-info">
    <div class="box-header"><h2 class="box-title">{{ __('govonboard::onboard.notices') }}</h2></div>
    <ul class="box-body list-unstyled">
        @forelse($notices as $notice)
        <li>{{ __('govonboard::onboard.event_'.$notice->event_key) }} @if($notice->office) — {{ $notice->office }} @endif <small>{{ $notice->created_at }}</small></li>
        @empty<li>{{ __('govonboard::onboard.no_notices') }}</li>@endforelse
    </ul>
</div>
