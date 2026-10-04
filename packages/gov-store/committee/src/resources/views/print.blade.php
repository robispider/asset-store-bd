@extends('layouts.default')
@section('title',__('committee::committee.print'))
@section('content')
<article class="box"><div class="box-body">
    @if($c->status === 'DRAFT')<h1>{{ __('committee::committee.draft') }} / DRAFT</h1>@endif
    <h1>{{ $c->name_bn }}</h1><h2>{{ $c->name_en }}</h2>
    <p>{{ $c->committee_number }} · {{ $c->effective_from }} — {{ $c->effective_to }}</p>
    <p>{{ __('committee::committee.as_of') }}: {{ $date->toDateString() }}</p>
    <p>{{ $c->terms_of_reference }}</p>
    <table class="table table-bordered"><thead><tr><th>{{ __('committee::committee.seat_no') }}</th><th>{{ __('committee::committee.seat_role_code') }}</th><th>{{ __('committee::committee.user_id') }}</th><th>{{ __('committee::committee.designation_bn') }}</th></tr></thead><tbody>
    @foreach($view->seats as $seat)<tr><td>{{ $seat->number }}</td><td>{{ $roles->firstWhere('code',$seat->role)?->name_bn }}</td><td>{{ $seat->holder?->nameBn }}<br>{{ $seat->holder?->nameEn }}</td><td>{{ $seat->holder?->designationBn }}</td></tr>@endforeach
    </tbody></table>
    <h2>{{ __('committee::committee.coverage') }}</h2>
    @foreach($coverage as $scope)<p>{{ $scope->scope_label_snapshot }} · {{ $scope->effective_from }} — {{ $scope->effective_to }}</p>@endforeach
    <h2>{{ __('committee::committee.orders') }}</h2>
    @foreach($orders as $order)<p>{{ $order['memo_no'] }} · {{ $order['issued_on'] }} · {{ $order['authority'] }}</p>@endforeach
    <h2>{{ __('committee::committee.draft_order') }}</h2>
    <textarea class="form-control" rows="10" readonly aria-label="{{ __('committee::committee.draft_order') }}">{{ $c->name_bn }} ({{ $c->name_en }})
{{ $c->terms_of_reference }}
@foreach($view->seats as $seat){{ $seat->number }}. {{ $roles->firstWhere('code',$seat->role)?->name_bn }}: {{ $seat->holder?->nameBn }}, {{ $seat->holder?->designationBn }}
@endforeach
{{ $c->effective_from }} — {{ $c->effective_to }}</textarea>
</div></article>
@stop
