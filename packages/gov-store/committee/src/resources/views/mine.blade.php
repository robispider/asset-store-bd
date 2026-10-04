@extends('layouts.default')
@section('title',__('committee::committee.mine'))
@section('content')
<div class="committee-workspace" data-base="{{ url('gov-store/committees') }}"><h1>{{ __('committee::committee.mine') }}</h1><div class="alert hidden cm-feedback" role="status" aria-live="polite"></div>
@foreach($all as $tenure)
    @php($committee = $committees[$tenure->committee_id] ?? null)
    @if($committee)<div class="box"><div class="box-body"><h2>{{ $committee->name_bn }}</h2><p>{{ $committee->committee_number }} · {{ $tenure->name_snapshot_bn }} · {{ $tenure->from_date }} — {{ $tenure->to_date }}</p>
        @if($tenure->declaration_status === 'FILED')<a href="{{ route('committee.own-declaration.file',$tenure->id) }}">{{ __('committee::committee.private_file') }}</a>@endif
        @if($tenure->declaration_status === 'PENDING' && in_array($committee->status,['DRAFT','ACTIVE']) && !$committee->tenures()->where('corrects_tenure_id',$tenure->id)->exists())
            <form class="cm-command" method="post" enctype="multipart/form-data" action="{{ route('committee.command.declare',['committee'=>$committee->id,'tenure'=>$tenure->id]) }}">@csrf
                @include('committee::field',['name'=>'filed_on','type'=>'date','value'=>$date->toDateString(),'required'=>true]) @include('committee::field',['name'=>'file','type'=>'file','required'=>true])
                <x-gov-action ability="committee.declare" type="submit" class="btn btn-primary">{{ __('committee::committee.declare') }}</x-gov-action>
            </form>
        @endif
    </div></div>@endif
@endforeach
@if($all->isEmpty())<p>{{ __('committee::committee.empty') }}</p>@endif
</div>
@stop
@section('moar_scripts')<script src="{{ mix('js/dist/committee.js') }}"></script>@stop
