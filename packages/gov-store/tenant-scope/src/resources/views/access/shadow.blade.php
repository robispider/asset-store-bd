@extends('layouts/default')
@section('title', __('tenantops::access.shadow'))
@section('content')
<div class="table-responsive"><table class="table table-striped"><caption>{{ __('tenantops::access.shadow') }}</caption><thead><tr>
@foreach(['office', 'abilities_label', 'roles', 'count'] as $label)<th scope="col">{{ __('tenantops::access.'.$label) }}</th>@endforeach
</tr></thead><tbody>@foreach($events as $event)<tr><td>{{ $event->location_id }}</td><td>{{ __('tenantops::access.abilities.'.str_replace('.', '_', $event->ability)) }}</td><td>{{ implode(', ', json_decode($event->roles, true)) }}</td><td>{{ $event->total }}</td></tr>@endforeach</tbody></table></div>{{ $events->links() }}
@endsection
