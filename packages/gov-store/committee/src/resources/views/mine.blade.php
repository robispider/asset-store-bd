@extends('committee::layout')
@section('committee-content')
<header class="cm-header"><h1>{{ __('committee::committee.mine') }}</h1><p class="cm-muted">{{ __('committee::committee.ux.current_count',['count'=>$display::digits($current->count())]) }}</p></header>
@forelse($current as $tenure)
    @php($committee = $committees[$tenure->committee_id])
    <article class="box">
    @if($tenure->from_date >= $date->subDays(7)->toDateString())<p class="cm-status">{{ __('committee::committee.ux.new_responsibility') }}</p>@endif
    <h2>{{ $display::text($committee->name_bn,$committee->name_en) }}</h2><p><strong>{{ __('committee::committee.ux.your_role') }}: {{ $display::text($tenure->seat->role->name_bn,$tenure->seat->role->name_en) }}</strong></p>
    <p class="cm-muted">{{ \Illuminate\Support\Facades\DB::table('locations')->where('id',$committee->owner_location_id)->value('name') }} · {{ $display::date($committee->effective_to) }}</p>
    @if((int)$committee->owner_location_id !== $context->locationId)<p class="cm-status cm-status-other"><i class="fa fa-building-o" aria-hidden="true"></i> {{ __('committee::committee.ux.other_committee') }}</p>@endif
    @include('committee::partials/status',['status'=>$committee->status === 'ACTIVE' ? app(\GovStore\Committee\Services\CommitteeHealthService::class)->computeFor($committee->id,$date)->status->value : $committee->status])
    <div class="cm-actions"><a class="btn btn-default" href="{{ route('committee.own-order.file',$tenure->id) }}">{{ __('committee::committee.ux.appointment_order') }}</a>@can('committee.view')<a class="btn btn-default" href="{{ route('committee.show',$committee->id) }}">{{ __('committee::committee.view') }}</a>@endcan</div>
    @if($tenure->declaration_status === 'FILED')<a class="btn btn-default" href="{{ route('committee.own-declaration.file',$tenure->id) }}">{{ __('committee::committee.private_file') }}</a>@endif
    @if($tenure->declaration_status === 'PENDING')<details><summary>{{ __('committee::committee.declare') }}</summary><form class="cm-command" method="post" enctype="multipart/form-data" action="{{ route('committee.command.declare',['committee'=>$committee->id,'tenure'=>$tenure->id]) }}">@csrf @include('committee::field',['name'=>'filed_on','type'=>'date','value'=>$date->toDateString(),'required'=>true])@include('committee::field',['name'=>'file','type'=>'file','required'=>true])<x-gov-action ability="committee.declare" type="submit" class="btn btn-primary">{{ __('committee::committee.declare') }}</x-gov-action></form></details>@endif
    </article>
@empty<section class="box cm-empty">{{ __('committee::committee.ux.no_current_committees') }}</section>@endforelse
<details class="box"><summary>{{ __('committee::committee.ux.past_committees') }} ({{ $display::digits($past->count()) }})</summary>@foreach($past as $tenure)@if($committee = $committees[$tenure->committee_id] ?? null)<p><strong>{{ $display::text($committee->name_bn,$committee->name_en) }}</strong> · {{ $display::text($tenure->seat?->role?->name_bn,$tenure->seat?->role?->name_en) }} · {{ $display::date($tenure->from_date) }} — {{ $display::date($tenure->to_date ?? $committee->effective_to) }}</p>@endif@endforeach</details>
<p class="cm-muted">{{ __('committee::committee.ux.member_help') }}</p>
@stop
