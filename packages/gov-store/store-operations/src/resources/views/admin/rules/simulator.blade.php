@extends('layouts/default')
@section('title', __('storeops::rules.simulator'))

@section('content')
<link rel="stylesheet" href="{{ url('css/dist/store-operations.css') }}">
<div class="row">
    <div class="col-md-12">
        <div class="box box-solid">
            <div class="box-header with-border" style="background: #f8fafc;">
                <h3 class="box-title" style="color: #1e293b; font-weight: bold;"><i class="fa fa-flask"></i> {{ __('storeops::storeops.rules_ui.the_why_simulator') }}</h3>
                <p class="text-muted" style="margin-top: 5px; font-size: 13px;">{{ __('storeops::storeops.rules_ui.test_how_multi_layer_policies_merge_in_the_real_world_see_exactl') }}</p>
            </div>
            <div class="box-body" style="padding: 20px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                <form id="simulatorForm" class="form-inline">
                    <div class="form-group" style="margin-right: 15px;">
                        <label style="margin-right: 10px; color: #475569;">{{ __('storeops::storeops.rules_ui.simulate_user_location') }}</label>
                        <select name="location_id" class="form-control" style="width: 250px;">
                            <option value="">{{ __('storeops::storeops.rules_ui.select_office_location') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group" style="margin-right: 15px;">
                        <label style="margin-right: 10px; color: #475569;">{{ __('storeops::storeops.rules_ui.receiving_target') }}</label>
                        <select name="category_id" class="form-control" style="width: 250px;">
                            <option value="">{{ __('storeops::storeops.rules_ui.select_category') }}</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fa fa-play"></i> {{ __('storeops::storeops.rules_ui.run_simulation') }}</button>
                </form>
            </div>
            
            <!-- Result Pane -->
            <div id="simulation_results" style="min-height: 400px; padding: 0;">
                <div class="text-center text-muted" style="margin-top: 100px;">
                    <i class="fa fa-cogs fa-4x" style="opacity: 0.2; margin-bottom: 20px;"></i>
                    <h4>{{ __('storeops::storeops.rules_ui.select_a_context_and_click_run_simulation') }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
@include('storeops::admin.rules.partials.client-config', ['rulesPage' => 'simulator'])
@endsection
