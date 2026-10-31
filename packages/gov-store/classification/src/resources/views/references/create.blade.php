@extends('layouts/default')
@section('title', __('tenantops::access.reference_create'))
@section('content')
<div class="box box-primary"><div class="box-header"><h1 class="box-title">{{ __('tenantops::access.reference_create') }}</h1></div><div class="box-body">
<p>{{ __('tenantops::access.reference_scope_notice') }}</p>
<form method="post" action="{{ route('gov.catalog.references.store') }}">@csrf
<label for="reference-type">{{ __('tenantops::access.reference_type') }}</label>
<select id="reference-type" name="reference_type" class="form-control">@foreach(['model', 'supplier', 'manufacturer'] as $type)<option value="{{ $type }}">{{ __('tenantops::access.reference_types.'.$type) }}</option>@endforeach</select>
<label for="reference-name">{{ __('general.name') }}</label><input id="reference-name" name="name" required maxlength="255" class="form-control" value="{{ old('name') }}">
<label for="reference-category">{{ __('general.category') }} ({{ __('tenantops::access.model_only') }})</label>
<select id="reference-category" name="category_id" class="form-control"><option value=""></option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
<label for="reference-manufacturer">{{ __('tenantops::access.reference_types.manufacturer') }} ({{ __('tenantops::access.model_only') }})</label>
<select id="reference-manufacturer" name="manufacturer_id" class="form-control"><option value=""></option>@foreach($manufacturers as $manufacturer)<option value="{{ $manufacturer->id }}">{{ $manufacturer->name }}</option>@endforeach</select>
<label for="reference-model-number">{{ __('tenantops::access.model_number') }}</label><input id="reference-model-number" name="model_number" class="form-control" maxlength="255">
<button class="btn btn-primary" type="submit">{{ __('general.save') }}</button>
</form></div></div>
@endsection
