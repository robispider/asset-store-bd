<div class="box box-default">
    <div class="box-header with-border">
        <h3 class="box-title"><i class="fas fa-history"></i> {{ __('requestlabels::requests.fulfillment_show_header_timeline') ?? 'Audit Timeline' }}</h3>
    </div>
    <div class="box-body">
        <ul class="timeline">
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

                    <div class="timeline-item" style="box-shadow: none; border: 1px solid #eee; background-color: #fafafa; margin-left: 45px;">
                        <span class="time"><i class="far fa-clock"></i> {{ $event->created_at->format('H:i') }}</span>
                        <h3 class="timeline-header" style="font-size: 13px; font-weight: bold; border-bottom: none; padding: 5px 10px;">
                            {{ ucwords(str_replace('_', ' ', $event->event_type)) }}
                        </h3>
                        <div class="timeline-body" style="padding: 5px 10px; font-size: 12px; color: #555;">
                            Executed by: <strong>{{ $event->user->display_name ?? 'System' }}</strong>
                            
                            @if($event->event_type === 'item_substituted' && isset($event->details['original']))
                                <p style="margin-top: 5px; margin-bottom: 0;">
                                    Swapped: <strong>{{ $event->details['original'] }}</strong> <br>
                                    With: <span class="text-orange" style="font-weight: bold;">{{ $event->details['substituted_with'] ?? '' }}</span>
                                </p>
                            @endif
                            
                            @if($event->event_type === 'item_issued' && isset($event->details['item']))
                                <p style="margin-top: 5px; margin-bottom: 0;">
                                    Issued: <strong>{{ $event->details['item'] }}</strong> (Qty: {{ $event->details['issued_qty'] ?? 0 }})
                                </p>
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
                    <div class="timeline-item" style="box-shadow: none; margin-left: 45px; background: transparent;">
                        <div class="timeline-body text-muted">No timeline events recorded yet.</div>
                    </div>
                </li>
            @endforelse
            @if($events->isNotEmpty())
                <li><i class="far fa-clock bg-gray"></i></li>
            @endif
        </ul>
    </div>
</div>