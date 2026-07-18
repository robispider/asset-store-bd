@extends('layouts/default')

@section('title', __('classification::texts.external_title'))

@section('content')

<div class="row">
    <div class="col-md-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">{{ __('classification::texts.external_header_title') }}</h3>
            </div>

            <div class="box-body">
                <p class="text-muted">
                    {{ __('classification::texts.external_desc') }}
                </p>

                <!-- Changed to a safe disabled placeholder button to prevent RouteNotFoundException -->
                <button class="btn btn-primary" disabled title="{{ __('classification::texts.external_btn_disabled') }}">
                    <i class="fas fa-plus"></i> {{ __('classification::texts.external_btn_disabled') }}
                </button>

                <table class="table table-striped table-bordered" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>{{ __('classification::texts.external_col_source_scheme') }}</th>
                            <th>{{ __('classification::texts.external_col_target_scheme') }}</th>
                            <th>{{ __('classification::texts.external_col_mapping_rule') }}</th>
                            <th>{{ __('classification::texts.external_col_status') }}</th>
                            <th>{{ __('classification::texts.external_col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="text-center text-muted">{{ __('classification::texts.external_empty_state') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection