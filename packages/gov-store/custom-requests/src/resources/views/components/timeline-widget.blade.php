<div class="box box-default request-timeline">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fas fa-history"></i> {{ __('requestlabels::requests.fulfillment_show_header_timeline') ?? 'Audit Timeline' }}</h3>
    </div>
    <div class="box-body">
        <ul class="timeline request-timeline">
            @forelse($events as $event)
                <li>
                    @if($event->event_type === 'draft_created')
                        <i class="fa fa-plus bg-blue"></i>
                    @elseif($event->event_type === 'submitted')
                        <i class="fa fa-paper-plane bg-yellow-active"></i>
                    @elseif($event->event_type === 'under_review')
                        <i class="fa fa-eye bg-purple"></i>
                    @elseif($event->event_type === 'item_substituted')
                        <i class="fas fa-exchange-alt bg-orange"></i>
                    @elseif($event->event_type === 'item_issued')
                        <i class="fa fa-truck bg-green-active"></i>
                    @elseif($event->event_type === 'closed')
                        <i class="fa fa-lock bg-green"></i>
                    @else
                        <i class="fa fa-info bg-gray"></i>
                    @endif

                    <div class="timeline-item">
                        <span class="time"><i class="far fa-clock"></i> {{ $event->created_at->format('H:i') }}</span>
                        <h3 class="timeline-header">
                            {{ __('requestlabels::requests.event_'.$event->event_type) }}
                        </h3>
                        <div class="timeline-body">
                            {{ __('requestlabels::requests.executed_by') }}: <strong>{{ $event->user->display_name ?? __('requestlabels::requests.system') }}</strong>
                            
                            @if($event->event_type === 'item_substituted' && isset($event->details['original']))
                                <p style="margin-top: 5px; margin-bottom: 0;">
                                    {{ __('requestlabels::requests.substituted_original') }}: <strong>{{ $event->details['original'] }}</strong> <br>
                                    {{ __('requestlabels::requests.substituted_with') }}: <span class="text-orange" style="font-weight: bold;">{{ $event->details['substituted_with'] ?? '' }}</span>
                                </p>
                            @endif
                            
                            @if($event->event_type === 'item_issued' && isset($event->details['item']))
                                <p style="margin-top: 5px; margin-bottom: 0;">
                                    {{ __('requestlabels::requests.issued_label') }}: <strong>{{ $event->details['item'] }}</strong> ({{ __('requestlabels::requests.quantity') }}: {{ $event->details['issued_qty'] ?? 0 }})
                                </p>
                            @endif
                            
                            @if(!empty($event->details['notes']) || !empty($event->details['reason']))
                                <p>{{ $event->details['notes'] ?? $event->details['reason'] }}</p>
                            @endif
                            @if(isset($event->details['message']))
                                <p style="margin-top: 5px; margin-bottom: 0;">{{ $event->details['message'] }}</p>
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li>
                    <i class="fa fa-clock bg-gray"></i>
                    <div class="timeline-item">
                        <div class="timeline-body text-muted">{{ __('requestlabels::requests.no_events') }}</div>
                    </div>
                </li>
            @endforelse
            @if($events->isNotEmpty())
                <li><i class="far fa-clock bg-gray"></i></li>
            @endif
        </ul>
    </div>
</div>
