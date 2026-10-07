@php
    $policy = $c->policy_snapshot ?? $c->type->composition_policy;
    $ruleGroups = [
        'strength'=>['codes'=>['BELOW_MIN_STRENGTH','ABOVE_MAX_STRENGTH','ODD_STRENGTH_REQUIRED'],'text'=>__('committee::committee.ux.strength_rule',['min'=>$display::digits($policy['strength']['min']),'max'=>$display::digits($policy['strength']['max'])])],
        'presiding'=>['codes'=>['PRESIDING_VACANT'],'text'=>__('committee::committee.ux.presiding_rule')],
    ];
    if ($policy['secretary']['min']) { $ruleGroups['secretary']=['codes'=>['SECRETARY_VACANT','TOO_MANY_SECRETARIES'],'text'=>__('committee::committee.ux.secretary_rule',['min'=>$display::digits($policy['secretary']['min'])])]; }
    if ($policy['external']['min']) { $ruleGroups['external']=['codes'=>['EXTERNAL_SHORTFALL'],'text'=>__('committee::committee.ux.external_rule',['min'=>$display::digits($policy['external']['min']),'boundary'=>__('committee::committee.options.'.$policy['external']['outside'])])]; }
    if ($policy['technical_expert']['min']) { $ruleGroups['technical_expert']=['codes'=>['TECHNICAL_EXPERT_SHORTFALL'],'text'=>__('committee::committee.ux.expert_rule',['min'=>$display::digits($policy['technical_expert']['min'])])]; }
@endphp
<aside class="box cm-rules"><h2>{{ __('committee::committee.ux.committee_rules') }}</h2><p class="cm-muted">{{ __('committee::committee.ux.rules_live') }}</p>
<ul class="list-unstyled">
@foreach($ruleGroups as $group=>$rule)
    @php($failed = collect($health->issues)->whereIn('code',$rule['codes'])->isNotEmpty())
    <li><i class="fa {{ $failed ? 'fa-times-circle' : 'fa-check' }}" aria-hidden="true"></i> {{ $rule['text'] }}
    @if(!empty($policy[$group]['reason_bn']) || !empty($policy[$group]['reason_en']))<p class="cm-muted">{{ $display::text($policy[$group]['reason_bn'] ?? '',$policy[$group]['reason_en'] ?? '') }}</p>@endif</li>
@endforeach
@foreach($health->issues as $finding)<li><i class="fa {{ $finding['severity'] === 'BLOCK' ? 'fa-times-circle' : 'fa-exclamation-triangle' }}" aria-hidden="true"></i> {{ __('committee::committee.issues.'.$finding['code']) }}
    @if(!empty($finding['reason_bn']) || !empty($finding['reason_en']))<p class="cm-muted">{{ $display::text($finding['reason_bn'] ?? '',$finding['reason_en'] ?? '') }}</p>@endif
    <a href="{{ route('committee.show',['committee'=>$c->id,'step'=>$finding['code'] === 'NO_COVERAGE' || $finding['code'] === 'TERM_TOO_LONG' ? 2 : 3]) }}">{{ __('committee::committee.ux.fix') }}</a></li>
@endforeach
@if(!$health->issues)<li><i class="fa fa-check" aria-hidden="true"></i> {{ __('committee::committee.ux.rules_met') }}</li>@endif
</ul>
@if(collect($health->issues)->contains('severity','BLOCK'))<p class="alert alert-warning">{{ __('committee::committee.ux.fix_before_confirm') }}</p>@endif
</aside>
