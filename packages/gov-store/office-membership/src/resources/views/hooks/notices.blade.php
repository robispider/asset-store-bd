<div class="box box-info">
    <div class="box-header"><h2 class="box-title">{{ __('office_membership::member.notices_title') }}</h2></div>
    <ul class="box-body list-unstyled">
        @forelse($notices as $notice)
            <li><strong>{{ $notice->location_name }}</strong>: {{ __('office_membership::member.notice_'.$notice->event_key) }} <small class="text-muted">{{ $notice->created_at }}</small></li>
        @empty
            <li>{{ __('office_membership::member.no_notices') }}</li>
        @endforelse
    </ul>
</div>
