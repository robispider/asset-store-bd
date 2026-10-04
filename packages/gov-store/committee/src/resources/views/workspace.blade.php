@extends('layouts.default')
@section('title', __('committee::committee.title'))
@section('content')
<div class="committee-workspace" data-base="{{ url('gov-store/committees') }}" data-committee="{{ $c?->id }}" data-today="{{ now('Asia/Dhaka')->toDateString() }}">
    <div class="box box-primary">
        <div class="box-header with-border"><h1 class="box-title">{{ __('committee::committee.title') }}</h1></div>
        <div class="box-body">
            <p>{{ __('committee::committee.inventory_only') }} {{ __('committee::committee.no_procurement') }}</p>
            <nav aria-label="{{ __('committee::committee.title') }}">
                @foreach(['dashboard','registry','new','mine','transfers','types','purposes','search'] as $nav)
                    <a class="btn btn-default" href="{{ route('committee.'.$nav) }}">{{ __('committee::committee.'.$nav) }}</a>
                @endforeach
            </nav>
        </div>
    </div>
    <div class="alert hidden cm-feedback" role="status" aria-live="polite"></div>
    @php
        $enumOptions = fn ($keys) => collect(explode(' ', $keys))->mapWithKeys(fn ($k) => [$k => __('committee::committee.options.'.$k)])->all();
        $typeOptions = $types->where('is_active',true)->mapWithKeys(fn ($t) => [$t->id => $t->name_bn.' / '.$t->name_en])->all();
        $orderOptions = collect($orders)->mapWithKeys(fn ($o) => [$o['id'] => $o['memo_no'].' — '.$o['kind']])->all();
        $own = $c && (int)$c->owner_location_id === app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId;
        $editable = $own && in_array($c->status,['DRAFT','ACTIVE']);
    @endphp
    @if($mode === 'new' || ($c && $c->status === 'DRAFT') || $mode === 'reconstitute')
        <div class="box box-success"><div class="box-header"><h2 class="box-title">{{ __('committee::committee.'.($mode === 'reconstitute' ? 'reconstitute' : 'new')) }}</h2></div><div class="box-body">
            <form class="cm-command" action="{{ $mode === 'reconstitute' ? route('committee.command.reconstitute',$c->id) : ($c ? route('committee.command.update',$c->id) : route('committee.command.create')) }}" method="post" @if($c && $mode !== 'reconstitute') data-method="PUT" @endif>
                @csrf
                <input type="hidden" name="lock_version" value="{{ $c?->lock_version ?? 0 }}">
                @include('committee::field',['name'=>'committee_type_id','label'=>'type','options'=>$typeOptions,'value'=>$c?->committee_type_id,'required'=>true])
                <div class="row"><div class="col-md-6">@include('committee::field',['name'=>'name_bn','value'=>$c?->name_bn,'required'=>true])</div><div class="col-md-6">@include('committee::field',['name'=>'name_en','value'=>$c?->name_en,'required'=>true])</div></div>
                @include('committee::field',['name'=>'term_basis','options'=>$enumOptions('FISCAL_YEAR FIXED SINGLE_MATTER UNTIL_FURTHER_ORDER'),'value'=>$c?->term_basis])
                <div class="row"><div class="col-md-6">@include('committee::field',['name'=>'effective_from','type'=>'date','value'=>$mode === 'reconstitute' ? now('Asia/Dhaka')->toDateString() : ($c?->effective_from ?? now('Asia/Dhaka')->toDateString()),'required'=>true])</div><div class="col-md-6">@include('committee::field',['name'=>'effective_to','type'=>'date','value'=>$c?->effective_to])</div></div>
                @include('committee::field',['name'=>'terms_of_reference','value'=>$c?->terms_of_reference])
                <x-gov-action ability="committee.manage" type="submit" class="btn btn-success">{{ __('committee::committee.'.($c ? 'save' : 'create')) }}</x-gov-action>
            </form>
        </div></div>
    @endif
    @if($c && $mode !== 'reconstitute')
        <div class="box box-primary"><div class="box-header"><h2 class="box-title">{{ $c->name_bn }} <small>{{ $c->name_en }}</small></h2></div><div class="box-body">
            <p><strong>{{ $c->committee_number }}</strong> · <i class="fa fa-info-circle" aria-hidden="true"></i> {{ __('committee::committee.options.'.$c->status) }} · {{ __('committee::committee.options.'.$health->status->value) }} · {{ $c->effective_from }} — {{ $c->effective_to }}</p>
            @if($c->status === 'DRAFT')<p class="alert alert-warning"><strong>{{ __('committee::committee.draft') }} / DRAFT</strong></p>@endif
            <form method="get" class="form-inline">@include('committee::field',['name'=>'as_of','type'=>'date','value'=>$date->toDateString()])<button class="btn btn-default">{{ __('committee::committee.view') }}</button></form>
            <a class="btn btn-default" href="{{ route('committee.history',$c->id) }}">{{ __('committee::committee.history') }}</a>
            <a class="btn btn-default" href="{{ route('committee.print',['committee'=>$c->id,'as_of'=>$date->toDateString()]) }}">{{ __('committee::committee.print') }}</a>
            @if($own && in_array($c->status,['ACTIVE','EXPIRED']))<a class="btn btn-default" href="{{ route('committee.reconstitute',$c->id) }}">{{ __('committee::committee.reconstitute') }}</a>@endif
        </div></div>
        <div class="row"><div class="col-md-8">
            <div class="box"><div class="box-header"><h2 class="box-title">{{ __('committee::committee.members') }}</h2></div><div class="box-body table-responsive">
                <table class="table table-striped"><thead><tr><th>{{ __('committee::committee.seat_no') }}</th><th>{{ __('committee::committee.seat_role_code') }}</th><th>{{ __('committee::committee.user_id') }}</th><th>{{ __('committee::committee.declaration') }}</th></tr></thead><tbody>
                    @foreach($view->seats as $seat)<tr><td>{{ $seat->number }}</td><td>{{ $roles->firstWhere('code',$seat->role)?->name_bn }}</td><td>{{ $seat->holder?->nameBn }}<br><small>{{ $seat->holder?->nameEn }} · {{ $seat->holder?->designationBn }}</small></td><td>{{ __('committee::committee.options.'.($seat->holder?->declarationStatus ?? 'PENDING')) }}</td></tr>@endforeach
                </tbody></table>
                @if($editable && $c->status === 'DRAFT')
                    <details><summary>{{ __('committee::committee.add_seat') }}</summary>
                    <form class="cm-command" method="post" action="{{ route('committee.command.seat',$c->id) }}">@csrf
                        @include('committee::field',['name'=>'seat_role_code','options'=>$roles->mapWithKeys(fn ($r) => [$r->code=>$r->name_bn.' / '.$r->name_en])->all()])
                        @include('committee::field',['name'=>'seat_no','type'=>'number','value'=>count($view->seats)+1,'required'=>true])
                        @include('committee::field',['name'=>'holder_kind','options'=>$enumOptions('PERSON POST EXTERNAL')])
                        @include('committee::field',['name'=>'post_title_en']) @include('committee::field',['name'=>'post_title_bn'])
                        <x-gov-action ability="committee.manage" type="submit" class="btn btn-primary">{{ __('committee::committee.add_seat') }}</x-gov-action>
                    </form></details>
                @endif
                @if($editable)
                    @foreach($view->seats as $seat)
                        @php($appointmentCommand = $seat->holder ? ($c->status === 'DRAFT' ? 'changeDraftHolder' : 'replace') : 'appoint')
                        <details><summary>{{ $seat->number }} · {{ __('committee::committee.'.($seat->holder ? 'replace' : 'appoint')) }}</summary>
                        <form class="cm-command" method="post" action="{{ route('committee.command.'.$appointmentCommand,['committee'=>$c->id,'seat'=>$seat->id]) }}" @if($seat->holder) data-confirm="true" @endif>@csrf
                            @if($seat->holder)<p>{{ $seat->holder->nameBn }} / {{ $seat->holder->nameEn }}</p>@endif
                            @if($seat->holderKind === 'EXTERNAL') @include('committee::field',['name'=>'external_member_id','options'=>[],'class'=>'cm-external'])
                            @else @include('committee::field',['name'=>'user_id','options'=>[],'class'=>'cm-people']) @include('committee::field',['name'=>'verification_code']) @endif
                            @include('committee::field',['name'=>'from_date','type'=>'date','value'=>$c->status === 'DRAFT' ? $c->effective_from : now('Asia/Dhaka')->toDateString(),'required'=>true])
                            @include('committee::field',['name'=>'order_id','options'=>$orderOptions])
                            @if($seat->holder && $c->status === 'ACTIVE') @include('committee::field',['name'=>'release_reason','options'=>$enumOptions('TRANSFER RETIREMENT PROMOTION RESIGNATION REMOVAL DEATH')]) @endif
                            @if($c->status === 'ACTIVE') @include('committee::acknowledgements') @endif
                            <x-gov-action ability="committee.manage" type="submit" class="btn btn-primary">{{ __('committee::committee.save') }}</x-gov-action>
                        </form></details>
                        @if($seat->holder && $c->status === 'ACTIVE')
                            <details><summary>{{ __('committee::committee.release') }} · {{ $seat->holder->nameBn }}</summary>
                            <form class="cm-command" method="post" data-confirm="true" action="{{ route('committee.command.release',['committee'=>$c->id,'tenure'=>$seat->holder->tenureId]) }}">@csrf
                                @include('committee::field',['name'=>'to_date','type'=>'date','required'=>true])
                                @include('committee::field',['name'=>'release_reason','options'=>$enumOptions('TRANSFER RETIREMENT PROMOTION RESIGNATION REMOVAL DEATH')])
                                @include('committee::field',['name'=>'order_id','options'=>$orderOptions]) @include('committee::acknowledgements')
                                <x-gov-action ability="committee.manage" type="submit" class="btn btn-warning">{{ __('committee::committee.release') }}</x-gov-action>
                            </form></details>
                        @endif
                    @endforeach
                    <details><summary>{{ __('committee::committee.external') }}</summary>@include('committee::external-form')</details>
                    <details><summary>{{ __('committee::committee.lookup') }}</summary>
                        <form class="cm-command cm-code" action="{{ route('committee.api.by-code') }}" method="post">@csrf @include('committee::field',['name'=>'code','required'=>true])<button class="btn btn-default" type="submit">{{ __('committee::committee.lookup') }}</button></form>
                    </details>
                @endif
            </div></div>
            <div class="box"><div class="box-header"><h2 class="box-title">{{ __('committee::committee.coverage') }}</h2></div><div class="box-body">
                @foreach($coverage as $s)<p>{{ $s->scope_label_snapshot }} · {{ $s->effective_from }} — {{ $s->effective_to }}
                    @if($editable && $c->status === 'ACTIVE' && !$s->effective_to)<details><summary>{{ __('committee::committee.withdraw') }}</summary><form class="cm-command" method="post" data-confirm="true" action="{{ route('committee.command.withdraw',['committee'=>$c->id,'scope'=>$s->id]) }}">@csrf @include('committee::field',['name'=>'effective_to','type'=>'date','required'=>true]) @include('committee::field',['name'=>'order_id','options'=>$orderOptions]) @include('committee::acknowledgements')<button class="btn btn-warning" type="submit">{{ __('committee::committee.withdraw') }}</button></form></details>@endif
                </p>@endforeach
                @if($editable)<form class="cm-command" action="{{ route('committee.command.scope',$c->id) }}" method="post">@csrf
                    @include('committee::field',['name'=>'scope_type','options'=>$enumOptions('office store'),'class'=>'cm-scope-type'])
                    @include('committee::field',['name'=>'scope_id','options'=>[],'class'=>'cm-scope-id'])
                    @include('committee::field',['name'=>'effective_from','type'=>'date','value'=>$c->effective_from,'required'=>true]) @include('committee::field',['name'=>'order_id','options'=>$orderOptions])
                    <x-gov-action ability="committee.manage" type="submit" class="btn btn-primary">{{ __('committee::committee.assign') }}</x-gov-action>
                </form>@endif
            </div></div>
            <div class="box"><div class="box-header"><h2 class="box-title">{{ __('committee::committee.orders') }}</h2></div><div class="box-body">
                @foreach($orders as $o)<p><strong>{{ $o['memo_no'] }}</strong> · {{ $o['issued_on'] }} · {{ __('committee::committee.options.'.$o['kind']) }} @if($o['file_url'])<a href="{{ $o['file_url'] }}">{{ __('committee::committee.private_file') }}</a>@endif</p>@endforeach
                @if($own && in_array($c->status,['DRAFT','ACTIVE','SUSPENDED','EXPIRED']))
                    <details><summary>{{ __('committee::committee.record_order') }}</summary>
                    <form class="cm-command" action="{{ route('committee.command.order',$c->id) }}" method="post" enctype="multipart/form-data">@csrf
                        @include('committee::field',['name'=>'kind','options'=>$enumOptions('CONSTITUTION AMENDMENT RECONSTITUTION EXTENSION SUSPENSION RESUMPTION DISSOLUTION CORRIGENDUM')])
                        @foreach(['memo_no','office_order_no','nothi_no','issuing_authority_name','issuing_authority_designation_en'] as $field) @include('committee::field',['name'=>$field,'required'=>!in_array($field,['office_order_no','nothi_no'])]) @endforeach
                        @include('committee::field',['name'=>'issued_on','type'=>'date','value'=>$c->effective_from,'required'=>true])
                        @include('committee::field',['name'=>'file','type'=>'file','required'=>true])
                        <x-gov-action ability="committee.manage" type="submit" class="btn btn-primary">{{ __('committee::committee.record_order') }}</x-gov-action>
                    </form></details>
                @endif
            </div></div>
        </div><div class="col-md-4">
            <div class="box box-warning"><div class="box-header"><h2 class="box-title">{{ __('committee::committee.composition') }}</h2></div><div class="box-body">
                @forelse($health->issues as $finding)<p><i class="fa fa-exclamation-circle" aria-hidden="true"></i> {{ __('committee::committee.issues.'.$finding['code']) }}</p>@empty<p><i class="fa fa-check" aria-hidden="true"></i> {{ __('committee::committee.options.OPERABLE') }}</p>@endforelse
                @if($own && $c->status === 'DRAFT')
                    <form class="cm-command" method="post" data-confirm="true" action="{{ route('committee.command.activate',$c->id) }}">@csrf
                        @include('committee::field',['name'=>'order_id','options'=>$orderOptions]) @include('committee::acknowledgements')
                        <x-gov-action ability="committee.activate" type="submit" :locked="collect($health->issues)->contains(fn ($f) => $f['severity'] === 'BLOCK')" class="btn btn-success">{{ __('committee::committee.activate') }}</x-gov-action>
                    </form>
                @endif
                @if($own && in_array($c->status,['ACTIVE','SUSPENDED','EXPIRED']))
                    @foreach(($c->status === 'ACTIVE' ? ['suspend','extend','dissolve'] : ($c->status === 'SUSPENDED' ? ['resume','dissolve'] : ['extend'])) as $action)
                    <details><summary>{{ __('committee::committee.'.$action) }}</summary>
                        <form class="cm-command" action="{{ route('committee.command.'.$action,$c->id) }}" method="post" data-confirm="true">@csrf
                            @include('committee::field',['name'=>'date','type'=>'date','value'=>now('Asia/Dhaka')->toDateString(),'required'=>true]) @include('committee::field',['name'=>'order_id','options'=>$orderOptions]) @include('committee::field',['name'=>'reason','required'=>true])
                            @if($action === 'extend') @include('committee::field',['name'=>'effective_to','type'=>'date','required'=>true]) @endif
                            @if($action === 'dissolve') @include('committee::field',['name'=>'confirmation','required'=>true]) @endif
                            <x-gov-action ability="committee.activate" type="submit" class="btn btn-warning">{{ __('committee::committee.'.$action) }}</x-gov-action>
                        </form>
                    </details>@endforeach
                    <h3>{{ __('committee::committee.impacts') }}</h3>
                    @foreach(app(\GovStore\Committee\Services\ImpactAnalyzer::class)->ifEnded($c->id) as $impact)<p>{{ $impact['purpose'] }} · {{ $impact['scope'] }}</p>@endforeach
                @endif
            </div></div>
        </div></div>
        <div class="box"><div class="box-header"><h2 class="box-title">{{ __('committee::committee.history') }}</h2></div><div class="box-body">
            <p><i class="fa {{ $chain ? 'fa-exclamation-triangle' : 'fa-check' }}" aria-hidden="true"></i> {{ __('committee::committee.'.($chain ? 'broken' : 'intact')) }}</p>
            @foreach($history as $entry)<p><strong>{{ $entry->event_type }}</strong> · {{ $entry->occurred_at }} · {{ $entry->actor_id }} · {{ $entry->reason }}</p>@endforeach
        </div></div>
        @foreach($tabs as $tab)<a class="btn btn-default" href="{{ $tab['url'] }}">{{ $tab['label'] }}</a>@endforeach
    @elseif(in_array($mode,['types','purposes']))
        @include('committee::catalog')
    @elseif($mode === 'transfers')
        @include('committee::transfers')
    @elseif($mode !== 'new')
        @if($mode === 'dashboard')
            <div class="box"><div class="box-header"><h2 class="box-title">{{ __('committee::committee.coverage') }}</h2></div><div class="box-body">
                @foreach($purposes as $purpose)
                    @php($resolution = app(\GovStore\Committee\Contracts\CommitteeResolver::class)->resolve($purpose->code,new \GovStore\Committee\DTOs\ScopeRef('office',(string)(app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId ?? 0)),$date))
                    <p>{{ $purpose->labelBn }} / {{ $purpose->labelEn }} · {{ $resolution->committee?->nameBn ?? __('committee::committee.empty') }}</p>
                @endforeach
            </div></div>
        @endif
        <div class="box"><div class="box-body">
            <form class="cm-search form-inline" action="{{ route('committee.api.registry') }}"><label for="cm-search">{{ __('committee::committee.search') }}</label> <input class="form-control" id="cm-search" name="q" placeholder="{{ __('committee::committee.search_placeholder') }}"><button class="btn btn-default" type="submit">{{ __('committee::committee.search') }}</button></form>
            <div class="table-responsive"><table class="table table-striped"><thead><tr><th>{{ __('committee::committee.number') }}</th><th>{{ __('committee::committee.name_bn') }}</th><th>{{ __('committee::committee.status') }}</th><th>{{ __('committee::committee.effective_to') }}</th></tr></thead><tbody class="cm-registry-rows">
                @forelse($rows as $row)<tr><td><a href="{{ route('committee.show',$row->id) }}">{{ $row->committee_number }}</a></td><td>{{ $row->name_bn }}<br><small>{{ $row->name_en }}</small></td><td>{{ __('committee::committee.options.'.$row->status) }}</td><td>{{ $row->effective_to }}</td></tr>@empty<tr><td colspan="4">{{ __('committee::committee.empty') }}</td></tr>@endforelse
            </tbody></table></div>{{ $rows->links() }}
        </div></div>
    @endif
</div>
<div class="modal fade" id="cm-confirm" tabindex="-1" role="dialog" aria-labelledby="cm-confirm-title"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h2 id="cm-confirm-title" class="modal-title">{{ __('committee::committee.confirm',[],'bn-BD') }}<small class="help-block">{{ __('committee::committee.confirm',[],'en-US') }}</small></h2></div>
    <div class="modal-body"><p>{{ __('committee::committee.backdated') }}</p><div class="cm-confirm-summary"></div></div>
    <div class="modal-footer"><button class="btn btn-default" data-dismiss="modal">{{ __('committee::committee.cancel') }}</button><button class="btn btn-primary cm-confirm-send">{{ __('committee::committee.continue') }}</button></div>
</div></div></div>
@stop
@section('moar_scripts')
<script src="{{ mix('js/dist/committee.js') }}"></script>
@stop
