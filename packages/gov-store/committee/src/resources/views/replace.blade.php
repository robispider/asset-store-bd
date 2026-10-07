@extends('committee::layout')
@section('committee-content')
@php
    $seat = collect($view->seats)->firstWhere('id',(int)request('seat')) ?? collect($view->seats)->first();
    $operation = $action === 'correct' ? 'correct' : ($action === 'release' ? 'release' : ($seat?->holder ? 'replace' : 'appoint'));
    $orderKind = $operation === 'correct' ? 'CORRIGENDUM' : 'AMENDMENT';
@endphp
<header class="cm-header"><h1>{{ __('committee::committee.ux.'.($operation === 'appoint' ? 'add_person' : $operation)) }}</h1><p class="cm-muted">{{ $display::text($c->name_bn,$c->name_en) }} · {{ $officeName }}</p></header>
<section class="box"><h2>{{ __('committee::committee.ux.who_leaves') }}</h2><div class="cm-actions">@foreach($view->seats as $candidate)<a class="btn {{ $candidate->id === $seat?->id ? 'btn-primary' : 'btn-default' }}" href="{{ route('committee.show',['committee'=>$c->id,'action'=>$action,'seat'=>$candidate->id]) }}">{{ $display::text($candidate->holder?->nameBn,$candidate->holder?->nameEn) ?: __('committee::committee.ux.vacant') }} · {{ $display::text($roles->firstWhere('code',$candidate->role)?->name_bn,$roles->firstWhere('code',$candidate->role)?->name_en) }}</a>@endforeach</div></section>
@include('committee::partials/working-order')
@if($seat && ($operation === 'appoint' || $seat->holder))
<section class="box"><form class="cm-command cm-member-change" method="post" data-confirm="true" data-operation="{{ $operation }}" data-seat="{{ $seat->id }}" data-tenure="{{ $seat->holder?->tenureId }}" action="{{ in_array($operation,['release','correct']) ? route('committee.command.'.$operation,['committee'=>$c->id,'tenure'=>$seat->holder->tenureId]) : route('committee.command.'.$operation,['committee'=>$c->id,'seat'=>$seat->id]) }}">@csrf<input type="hidden" name="order_id" value="{{ $workingOrder['id'] ?? '' }}">
<div class="cm-subtle"><strong>{{ $display::text($seat->holder?->nameBn,$seat->holder?->nameEn) }}</strong><p>{{ $display::text($seat->holder?->designationBn,$seat->holder?->designationEn) }}</p></div>
@if(in_array($operation,['replace','release']))<fieldset><legend>{{ __('committee::committee.ux.why_leaves') }}</legend><div class="cm-actions">@foreach(explode(' ','TRANSFER RETIREMENT PROMOTION RESIGNATION REMOVAL DEATH') as $reason)<label class="btn btn-default"><input type="radio" name="release_reason" value="{{ $reason }}" @checked($reason === 'TRANSFER')> {{ __('committee::committee.options.'.$reason) }}</label>@endforeach</div></fieldset>@endif
@if($operation !== 'release')
    <h2>{{ __('committee::committee.ux.who_arrives') }}</h2>
    @if($seat->holderKind === 'EXTERNAL')@include('committee::field',['name'=>'external_member_id','label'=>'ux.person','options'=>[],'class'=>'cm-external','required'=>true])
    @else @include('committee::field',['name'=>'user_id','label'=>'ux.person','options'=>[],'class'=>'cm-people','required'=>true])@include('committee::field',['name'=>'verification_code'])<button class="btn btn-default cm-change-lookup" type="button">{{ __('committee::committee.lookup') }}</button>@endif
@endif
@include('committee::field',['name'=>$operation === 'release' ? 'to_date' : 'from_date','label'=>$operation === 'release' ? 'to_date' : 'from_date','type'=>'date','value'=>$operation === 'correct' ? $seat->holder->fromDate : ($leavingDate ?? now('Asia/Dhaka')->toDateString()),'required'=>true])
@include('committee::acknowledgements')
<div class="cm-preview" role="status" aria-live="polite"><h3>{{ __('committee::committee.ux.preview') }}</h3><p class="cm-proposed-person">{{ __('committee::committee.ux.preview_help') }}</p><p class="cm-muted">{{ __('committee::committee.ux.server_rechecks') }}</p></div>
<div class="cm-actions"><a class="btn btn-default" href="{{ route('committee.show',$c->id) }}">{{ __('committee::committee.cancel') }}</a><x-gov-action ability="committee.manage" type="submit" :locked="!$workingOrder" class="btn btn-primary">{{ __('committee::committee.ux.confirm_change') }}</x-gov-action></div>
</form>@if($operation === 'replace')<a class="btn btn-default" href="{{ route('committee.show',['committee'=>$c->id,'action'=>'release','seat'=>$seat->id]) }}">{{ __('committee::committee.ux.leave_vacant') }}</a>@endif</section>
@else<p class="cm-card cm-empty">{{ __('committee::committee.ux.no_members') }}</p>@endif
@stop
