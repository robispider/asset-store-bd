@extends('layouts/default')
@section('title', __('tenantops::access.audit'))
@section('content')
<form method="get" class="form-inline">
@foreach(['location_id' => 'office', 'ability' => 'abilities_label', 'outcome' => 'outcome'] as $key => $label)<div class="form-group"><label for="filter-{{ $key }}">{{ __('tenantops::access.'.$label) }}</label><input id="filter-{{ $key }}" name="{{ $key }}" value="{{ request($key) }}" class="form-control"></div>@endforeach
<button type="submit" class="btn btn-default">{{ __('tenantops::access.filter') }}</button></form>
<div class="table-responsive"><table class="table table-striped"><caption>{{ __('tenantops::access.audit') }}</caption><thead><tr>
@foreach(['date', 'actor', 'office', 'abilities_label', 'outcome', 'reason', 'reference'] as $label)<th scope="col">{{ __('tenantops::access.'.$label) }}</th>@endforeach
</tr></thead><tbody>@foreach($events as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->user_id }}</td><td>{{ $event->location_id }}</td><td>{{ $event->ability }}</td><td>{{ $event->outcome }}</td><td>{{ $event->reason }}</td><td>{{ $event->reference_id }}</td></tr>@endforeach</tbody></table></div>{{ $events->links() }}
@endsection
