<section class="box"><h2>{{ __('committee::committee.ux.which_order') }}</h2>
@include('committee::partials/order-summary')
<details @if(!$workingOrder) open @endif><summary>{{ __('committee::committee.ux.record_new_order') }}</summary>
<form class="cm-command" action="{{ route('committee.command.order',$c->id) }}" method="post" enctype="multipart/form-data">@csrf
<input type="hidden" name="kind" value="{{ $orderKind ?? 'AMENDMENT' }}">
@include('committee::partials/order-fields')
<x-gov-action ability="committee.manage" type="submit" class="btn btn-primary">{{ __('committee::committee.record_order') }}</x-gov-action>
</form></details></section>
