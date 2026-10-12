@extends('layouts/default')
@section('title', __('tenantops::access.matrix'))
@section('content')
<div class="tenant-scope-theme">
<a class="btn btn-default" href="{{ route('gov.access.matrix', ['format' => 'csv']) }}">{{ __('tenantops::access.csv') }}</a>
<div class="table-responsive"><x-gs::table class="table table-bordered"><caption>{{ __('tenantops::access.matrix') }}</caption>
<thead><tr><th scope="col">{{ __('tenantops::access.abilities_label') }}</th>@foreach($roles as $role)<th scope="col">{{ $role }}</th>@endforeach<th scope="col">{{ __('tenantops::access.routes') }}</th></tr></thead>
<tbody>@foreach($matrix as $ability => $definition)<tr><th scope="row">{{ __('tenantops::access.abilities.'.str_replace('.', '_', $ability)) }}</th>@foreach($roles as $role)<td>{{ $role === 'superuser' || in_array($role, $definition['roles']) ? '✓' : '—' }}</td>@endforeach<td><ul>@foreach($routes[$ability] ?? [] as $route)<li>{{ $route }}</li>@endforeach</ul></td></tr>@endforeach</tbody>
</x-gs::table></div>

</div>
@endsection
