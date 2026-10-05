@if($workingOrder)
<div class="cm-subtle"><strong>{{ __('committee::committee.'.(($action ?? null) ? 'ux.working_order' : 'options.'.$workingOrder['kind'])) }}:</strong> {{ $workingOrder['memo_no'] }} · {{ $display::date($workingOrder['issued_on']) }}
@if($workingOrder['issued_on_bangla'] ?? null)<span> · {{ $workingOrder['issued_on_bangla'] }}</span>@endif
@if($workingOrder['file_url'])<a class="btn btn-default" href="{{ $workingOrder['file_url'] }}" target="_blank" rel="noopener">{{ __('committee::committee.ux.view_order') }}</a>@endif</div>
@endif
