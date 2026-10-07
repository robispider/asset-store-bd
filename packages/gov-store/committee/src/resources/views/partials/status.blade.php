@php
    $statusValue = $status ?? 'DRAFT';
    $style = match ($statusValue) { 'OPERABLE'=>'ready','AT_RISK','MISSING'=>'attention','INOPERABLE','SUSPENDED','DISSOLVED','EXPIRED'=>'block',default=>'' };
    $icon = match ($style) { 'ready'=>'check','attention'=>'exclamation-triangle','block'=>'times-circle',default=>'file-text-o' };
@endphp
<span class="cm-status cm-status-{{ $style }}"><i class="fa fa-{{ $icon }}" aria-hidden="true"></i> {{ $statusValue === 'MISSING' ? __('committee::committee.ux.missing') : __('committee::committee.options.'.$statusValue) }}</span>
