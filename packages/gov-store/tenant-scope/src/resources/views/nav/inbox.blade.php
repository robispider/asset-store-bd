{{-- Navbar inbox: the "Waiting for me" queues with their counts. Reads the same per-request
     snapshot as the sidebar, so each badge counter still runs at most once per page. --}}
@php($inboxItems = app(\GovStore\TenantScope\Navigation\MenuRegistry::class)->groupItems('gov-queues'))
@if($inboxItems)
    @php($inboxTotal = array_sum(array_map(fn ($inboxItem) => $inboxItem->badgeCount() ?? 0, $inboxItems)))
    <li class="dropdown notifications-menu gs-nav-inbox" id="gov-nav-inbox">
        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false"
           aria-label="{{ __('tenantops::nav.inbox_label', ['count' => $inboxTotal]) }}">
            <i class="fas fa-inbox fa-fw" aria-hidden="true"></i>
            @if($inboxTotal)
                <span class="label label-warning gs-nav-inbox-count">{{ $inboxTotal }}</span>
            @endif
            <span class="hidden-xs hidden-sm">{{ __('tenantops::nav.queues') }}</span>
        </a>
        <ul class="dropdown-menu">
            <li class="header">{{ $inboxTotal ? __('tenantops::nav.inbox_label', ['count' => $inboxTotal]) : __('tenantops::nav.inbox_empty') }}</li>
            <li>
                <ul class="menu">
                    @foreach($inboxItems as $inboxItem)
                        @php($inboxBadge = $inboxItem->badgeCount())
                        <li id="inbox-{{ $inboxItem->id }}">
                            <a href="{{ route($inboxItem->route) }}" @if($inboxItem->isActive()) aria-current="page" @endif>
                                <i class="{{ $inboxItem->icon }}" aria-hidden="true"></i>
                                {{ $inboxItem->label() }}
                                @if($inboxBadge)
                                    <span class="label label-warning pull-right">{{ $inboxBadge }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
        </ul>
    </li>
@endif
