@extends('layouts/default')
@section('title', __('tenantops::access.national_review'))
@section('content')
<div class="tenant-scope-theme">
<div class="box box-warning"><div class="box-header with-border"><h1 class="box-title" lang="bn">{{ trans('tenantops::access.national_review', [], 'bn-BD') }}</h1><p lang="en">{{ trans('tenantops::access.national_review', [], 'en-US') }}</p></div><div class="box-body">
<p lang="bn">{{ trans('tenantops::access.national_warning', [], 'bn-BD') }}</p><p lang="en">{{ trans('tenantops::access.national_warning', [], 'en-US') }}</p>
<p>{{ __('tenantops::access.abilities.'.str_replace('.', '_', $ability)) }}</p>
<h2 class="h4">{{ __('tenantops::access.impact') }}</h2><p>{{ __('tenantops::access.impact_upper_bound') }}</p><ul>@foreach($impact as $kind => $count)<li>{{ __('tenantops::access.'.$kind) }}: {{ $count }}</li>@endforeach</ul>
<div class="row"><div class="col-md-6"><h2 class="h4">{{ __('tenantops::access.before') }}</h2><pre>{{ json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div><div class="col-md-6"><h2 class="h4">{{ __('tenantops::access.after') }}</h2><pre>{{ json_encode($review['input'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div></div>
<form method="post" action="{{ $target }}">@csrf
<input type="hidden" name="review_token" value="{{ $token }}">
<div class="form-group"><label for="changeReason">{{ __('tenantops::access.change_reason') }}</label><textarea name="change_reason" id="changeReason" class="form-control" required minlength="5" maxlength="1000">{{ old('change_reason') }}</textarea></div>
<div class="form-group"><label for="changeConfirm">{{ __('tenantops::access.confirm_label') }}</label><input name="confirmation" id="changeConfirm" class="form-control" required pattern="CHANGE" autocomplete="off"></div>
<button class="btn btn-primary" type="submit">{{ __('tenantops::access.confirm') }}</button><a href="{{ route('gov.access.index') }}" class="btn btn-default">{{ __('tenantops::access.back') }}</a>
</form></div></div>

</div>
@endsection
