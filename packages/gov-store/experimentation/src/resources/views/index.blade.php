@extends('layouts/default')
@section('title', __('experiments::ui.title'))
@section('content')
<div class="box box-primary">
    <div class="box-header with-border"><h2 class="box-title">{{ __('experiments::ui.title') }}</h2></div>
    <div class="box-body">
        <p>{{ __('experiments::ui.description') }}</p>
        @foreach ($errors->all() as $error)<div class="alert alert-danger">{{ $error }}</div>@endforeach
        <form method="POST" action="{{ route('gov.experiments.populate') }}">@csrf
            <div class="form-group"><label for="label">{{ __('experiments::ui.label') }}</label><input class="form-control" id="label" name="label" maxlength="150" value="{{ old('label') }}"></div>
            <div class="form-group"><label for="profile">{{ __('experiments::ui.profile') }}</label><select class="form-control" id="profile" name="profile">@foreach ($profiles as $key => $size)<option value="{{ $key }}" {{ old('profile', 'government') === $key ? 'selected' : '' }}>{{ __('experiments::ui.profile_'.$key) }} — {{ $size['offices'] }} {{ __('experiments::ui.offices') }}, {{ $size['users'] }} {{ __('experiments::ui.people') }}, {{ $size['assets'] }} {{ __('experiments::ui.assets') }}</option>@endforeach</select></div>
            <div class="form-group"><label for="seed">{{ __('experiments::ui.seed') }}</label><input class="form-control" type="number" id="seed" name="seed" min="0" max="2147483647" required value="{{ old('seed', 2026) }}"></div>
            <button class="btn btn-primary" type="submit"><i class="fa fa-plus"></i> {{ __('experiments::ui.populate') }}</button>
        </form>
    </div>
</div>
<div class="box"><div class="box-body table-responsive"><table class="table table-striped"><thead><tr><th>{{ __('experiments::ui.dataset') }}</th><th>{{ __('experiments::ui.profile') }}</th><th>{{ __('experiments::ui.status') }}</th><th>{{ __('experiments::ui.phase') }}</th></tr></thead><tbody>@forelse ($runs as $run)<tr><td><a href="{{ route('gov.experiments.show', $run) }}">{{ $run->label }}</a></td><td>{{ __('experiments::ui.profile_'.$run->profile) }}</td><td>{{ $run->status }}</td><td>{{ $run->phase }}</td></tr>@empty<tr><td colspan="4">{{ __('experiments::ui.empty') }}</td></tr>@endforelse</tbody></table>{{ $runs->links() }}</div></div>
@endsection
