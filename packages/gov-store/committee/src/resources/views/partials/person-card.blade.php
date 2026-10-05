<article class="box cm-person">
    <span class="cm-status">{{ $display::digits($seat->number) }} · {{ $display::text($roles->firstWhere('code',$seat->role)?->name_bn,$roles->firstWhere('code',$seat->role)?->name_en) }}</span>
    <div class="cm-person-name"><span class="cm-avatar" aria-hidden="true">{{ mb_substr($display::text($seat->holder?->nameBn,$seat->holder?->nameEn),0,1) }}</span><div><h3>{{ $seat->holder ? $display::text($seat->holder->nameBn,$seat->holder->nameEn) : __('committee::committee.ux.vacant') }}</h3><small>{{ $display::text($seat->holder?->designationBn,$seat->holder?->designationEn) }}</small></div></div>
    @if($seat->holder)<p class="cm-muted">{{ __('committee::committee.ux.since',['date'=>$display::date($seat->holder->fromDate)]) }}</p>@endif
    @if($seat->holder?->isExternal)<p><span class="cm-status cm-status-other"><i class="fa fa-building-o" aria-hidden="true"></i> {{ __('committee::committee.ux.other_office') }}</span></p>@endif
    @if($seat->holderKind === 'POST')<p class="cm-muted">{{ __('committee::committee.ux.by_post') }} · {{ $display::text($seat->postTitleBn,$seat->postTitleEn) }}</p>@endif
    @if($own && $c->status === 'ACTIVE')<a class="btn btn-default" href="{{ route('committee.show',['committee'=>$c->id,'action'=>'replace','seat'=>$seat->id]) }}">{{ __('committee::committee.ux.'.($seat->holder ? 'replace' : 'add_person')) }}</a>@endif
</article>
