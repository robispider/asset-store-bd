@extends('layouts/default')

@section('title', __('govtracking::general.ui.extra_system_configuration_funding_sources'))

@section('content')
<div class="row">
    <div class="col-md-4">
        <form action="{{ route('gov.tracking.funding-types.store') }}" method="POST">
            @csrf
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">{{ __('govtracking::general.ui.add_new_funding_source') }}</h3></div>
                <div class="box-body">
                    <div class="form-group">
                        <label>{{ __('govtracking::general.ui.primary_segment_type') }}</label>
                        <select name="primary_type" class="form-control" required>
                            <option value="ADP">{{ __('govtracking::general.ui.adp_development_budget') }}</option>
                            <option value="REVENUE">{{ __('govtracking::general.ui.revenue_budget_non_development') }}</option>
                            <option value="OTHER">{{ __('govtracking::general.ui.other_sources_autonomous') }}</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('govtracking::general.ui.sub_source_name') }}</label>
                        <input type="text" name="name" class="form-control" placeholder="{{ __('govtracking::general.ui.extra_e_g_project_aid_pa_gob_taka') }}" required>
                    </div>
                    <div class="form-group">
                        <label>{{ __('govtracking::general.ui.description_optional') }}</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <button type="submit" class="btn btn-primary">{{ __('govtracking::general.ui.save_funding_source') }}</button>
                </div>
            </div>
        </form>
    </div>
    <div class="col-md-8">
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">{{ __('govtracking::general.ui.active_funding_sources_dictionary') }}</h3></div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('govtracking::general.ui.primary_segment') }}</th>
                            <th>{{ __('govtracking::general.ui.sub_source_name') }}</th>
                            <th>{{ __('govtracking::general.ui.description') }}</th>
                            <th>{{ __('govtracking::general.ui.active_task_count') }}</th>
                            <th class="text-right">{{ __('govtracking::general.ui.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fundingTypes as $type)
                            <tr>
                                <td><span class="label label-{{ $type->primary_type == 'ADP' ? 'success' : ($type->primary_type == 'REVENUE' ? 'primary' : 'default') }}">{{ $type->primary_type }}</span></td>
                                <td><strong>{{ $type->name }}</strong></td>
                                <td>{{ $type->description }}</td>
                                <td>{{ $type->tracking_codes_count }}</td>
                                <td class="text-right">
                                    <form action="{{ route('gov.tracking.funding-types.destroy', $type->id) }}" method="POST">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-xs btn-danger" {{ $type->tracking_codes_count > 0 ? 'disabled' : '' }} data-tracking-confirm="{{ __('govtracking::general.ui.confirm_funding') }}"><i class="fa fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted">{{ __('govtracking::general.ui.no_funding_sources_configured_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop