@extends('committee::layout')
@section('committee-content')
<header class="cm-header"><h1>{{ __('committee::committee.ux.transferred') }}</h1><p class="cm-muted">{{ __('committee::committee.ux.transfer_intro') }}</p></header>
<form class="cm-command cm-transfer-form" method="post" enctype="multipart/form-data" action="{{ route('committee.command.transfer') }}" data-confirm="true">@csrf<input type="hidden" name="order_flow" value="1"><input type="hidden" name="kind" value="AMENDMENT">
<section class="box">@include('committee::field',['name'=>'user_id','label'=>'ux.transferred_person','options'=>[],'class'=>'cm-transfer-person','required'=>true])<p class="cm-muted">{{ __('committee::committee.ux.choose_person') }}</p></section>
<section class="box"><h2>{{ __('committee::committee.ux.their_committees') }}</h2><div class="cm-transfer-seats" aria-live="polite"></div></section>
<section class="box"><h2>{{ __('committee::committee.ux.which_order') }}</h2>@include('committee::partials/order-fields')@include('committee::acknowledgements')<p>{{ __('committee::committee.ux.transfer_atomic') }}</p></section>
<div class="cm-savebar"><span>{{ __('committee::committee.ux.transfer_atomic') }}</span><x-gov-action ability="committee.manage" type="submit" class="btn btn-default cm-transfer-submit">{{ __('committee::committee.ux.save_all') }}</x-gov-action></div>
</form>
@stop
