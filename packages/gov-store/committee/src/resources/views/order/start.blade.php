@extends('committee::layout')
@section('committee-content')
<header class="cm-header"><div class="cm-toolbar"><h1>{{ __('committee::committee.ux.new_order') }}</h1><a class="btn btn-default" href="{{ route('committee.dashboard') }}">{{ __('committee::committee.ux.back_desk') }}</a></div></header>
@if(!$typeOptions)<p class="alert alert-warning">{{ __('committee::committee.ux.no_active_types') }}</p>@endif
@include('committee::partials/stepper',['step'=>1])
@if($c && $mode !== 'reconstitute')
    @include('committee::partials/working-order',['orderKind'=>$c->supersedes_id ? 'RECONSTITUTION' : 'CONSTITUTION'])
    @if($workingOrder)<a class="btn btn-primary" href="{{ route('committee.show',['committee'=>$c->id,'step'=>2]) }}">{{ __('committee::committee.ux.next_committee') }}</a>@endif
@else
<form class="cm-command cm-order-flow {{ $mode === 'new' ? 'cm-intake' : '' }}" method="post" enctype="multipart/form-data" action="{{ $mode === 'reconstitute' ? route('committee.command.reconstitute',$c->id) : route('committee.command.create') }}">@csrf<input type="hidden" name="order_flow" value="1">
@if($mode === 'new')<input type="hidden" name="intake_location_id" value="{{ $context->locationId }}"><input type="hidden" name="intake_company_id" value="{{ $context->companyId }}"><input type="hidden" name="intake_revision" value="{{ $intake['revision'] }}"><script type="application/json" class="cm-intake-data">{!! json_encode($intake,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script><p class="cm-draft-save" role="status" aria-live="polite">{{ __('committee::committee.ux.intake_help') }}</p>@endif
<div class="cm-order-first row"><section class="col-md-5"><div class="box"><h2>{{ __('committee::committee.ux.take_order') }}</h2>@include('committee::partials/order-fields')</div></section>
<section class="col-md-7"><div class="box"><h2>{{ __('committee::committee.ux.what_order_does') }}</h2><div class="cm-choice-grid">
@foreach(['form','replace','extend','reconstitute','suspend','resume','dissolve','correct'] as $job)<label class="cm-choice"><input type="radio" name="job" value="{{ $job }}" @checked($job === ($mode === 'reconstitute' ? 'reconstitute' : 'form'))><strong>{{ __('committee::committee.ux.job_'.$job) }}</strong><small>{{ __('committee::committee.ux.job_'.$job.'_help') }}</small></label>@endforeach
</div><div class="cm-job-target hidden">@include('committee::field',['name'=>'target_committee','label'=>'ux.select_committee','options'=>[''=>__('committee::committee.ux.choose')] + $targets->mapWithKeys(fn ($committee) => [$committee->id=>$display::text($committee->name_bn,$committee->name_en)])->all()])<button class="btn btn-primary cm-record-job" type="button">{{ __('committee::committee.ux.record_new_order') }}</button></div><div class="cm-actions"><button type="button" class="btn btn-primary cm-order-next">{{ __('committee::committee.ux.next_committee') }} →</button></div></div></section></div>
<div class="cm-order-second cm-hidden">@include('committee::order/committee-form')<div class="cm-actions"><button class="btn btn-default cm-order-back" type="button">← {{ __('committee::committee.ux.previous') }}</button><x-gov-action ability="committee.manage" type="submit" :locked="!$typeOptions" class="btn btn-primary">{{ __('committee::committee.ux.save_and_members') }}</x-gov-action></div></div>
@if($mode === 'new')<button type="button" class="btn btn-default cm-keep-intake">{{ __('committee::committee.ux.keep_draft') }}</button>@endif
</form>
@if($mode === 'new' && $intake['revision'])<form class="cm-command cm-discard-intake" method="post" data-method="DELETE" data-confirm="true" action="{{ route('committee.intake.discard') }}">@csrf<input type="hidden" name="intake_location_id" value="{{ $context->locationId }}"><input type="hidden" name="intake_company_id" value="{{ $context->companyId }}"><input type="hidden" name="intake_revision" value="{{ $intake['revision'] }}"><button type="submit" class="btn btn-default">{{ __('committee::committee.ux.discard_intake') }}</button></form>@endif
@endif
@stop
