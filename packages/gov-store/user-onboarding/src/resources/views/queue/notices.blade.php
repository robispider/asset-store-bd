<x-gs::box :title="__('govonboard::onboard.notices')" icon="fas fa-bell" tone="info">
    <ul class="list-unstyled">
        @forelse($notices as $notice)
        <li>{{ __('govonboard::onboard.event_'.$notice->event_key) }} @if($notice->office) — {{ $notice->office }} @endif <small>{{ $notice->created_at }}</small></li>
        @empty<li>{{ __('govonboard::onboard.no_notices') }}</li>@endforelse
    </ul>
</x-gs::box>
