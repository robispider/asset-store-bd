@extends('committee::layout')
@section('committee-content')
@php($orderKind = match ($action) {'extend'=>'EXTENSION','suspend'=>'SUSPENSION','resume'=>'RESUMPTION',default=>'AMENDMENT'})
<header class="cm-header"><h1>{{ __('committee::committee.ux.'.$action) }}</h1><p class="cm-muted">{{ $display::text($c->name_bn,$c->name_en) }} · {{ $officeName }}</p></header>
@include('committee::partials/working-order')
<section class="box">
@if($action === 'scope')
    <h2>{{ __('committee::committee.ux.offices_served') }}</h2>
    @foreach($coverage as $scope)<div class="cm-subtle"><strong>{{ $scope->scope_label_snapshot }}</strong><p>{{ $display::date($scope->effective_from) }} — {{ $display::date($scope->effective_to) }}</p>@if(!$scope->effective_to)<details><summary>{{ __('committee::committee.ux.withdraw') }}</summary><form class="cm-command" data-confirm="true" method="post" action="{{ route('committee.command.withdraw',['committee'=>$c->id,'scope'=>$scope->id]) }}">@csrf<input type="hidden" name="order_id" value="{{ $workingOrder['id'] ?? '' }}">@include('committee::field',['name'=>'effective_to','type'=>'date','required'=>true])@include('committee::acknowledgements')<x-gov-action ability="committee.manage" type="submit" :locked="!$workingOrder" class="btn btn-default">{{ __('committee::committee.ux.withdraw') }}</x-gov-action></form></details>@endif</div>@endforeach
    <form class="cm-command" action="{{ route('committee.command.scope',$c->id) }}" method="post">@csrf<input type="hidden" name="order_id" value="{{ $workingOrder['id'] ?? '' }}">@include('committee::field',['name'=>'scope_type','label'=>'ux.office_or_store','options'=>$enumOptions('office store'),'class'=>'cm-scope-type'])@include('committee::field',['name'=>'scope_id','label'=>'ux.offices_served','options'=>[],'class'=>'cm-scope-id','required'=>true])@include('committee::field',['name'=>'effective_from','type'=>'date','value'=>now('Asia/Dhaka')->toDateString(),'required'=>true])<x-gov-action ability="committee.manage" type="submit" :locked="!$workingOrder" class="btn btn-primary">{{ __('committee::committee.ux.add_office') }}</x-gov-action></form>
@elseif($action !== 'order')
    <form class="cm-command" data-confirm="true" action="{{ route('committee.command.'.$action,$c->id) }}" method="post">@csrf<input type="hidden" name="order_id" value="{{ $workingOrder['id'] ?? '' }}">@include('committee::field',['name'=>'date','type'=>'date','value'=>now('Asia/Dhaka')->toDateString(),'required'=>true])
    @if($action === 'extend')<p class="cm-subtle">{{ __('committee::committee.ux.current_end') }}: {{ $display::date($c->effective_to) }}</p>@include('committee::field',['name'=>'effective_to','label'=>'ux.new_end','type'=>'date','required'=>true])@include('committee::acknowledgements')@else @include('committee::field',['name'=>'reason','type'=>'textarea','required'=>true])<p class="cm-subtle">{{ __('committee::committee.ux.effect_'.$action) }}</p>@endif
    <div class="cm-actions"><a class="btn btn-default" href="{{ route('committee.show',$c->id) }}">{{ __('committee::committee.cancel') }}</a><x-gov-action ability="committee.activate" type="submit" :locked="!$workingOrder" class="btn btn-primary">{{ __('committee::committee.ux.confirm_change') }}</x-gov-action></div></form>
@endif
</section>
@stop
