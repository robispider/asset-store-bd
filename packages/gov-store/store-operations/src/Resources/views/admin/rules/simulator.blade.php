@extends('layouts/default')
@section('title', __('storeops::rules.simulator'))

@section('content')
<div class="storeops-theme">
<link rel="stylesheet" href="{{ url('css/dist/store-operations.css') }}">
<div class="row">
    <div class="col-md-12">
        <div class="box box-solid">
            <div class="box-header with-border storeops-inline-136" >
                <h3 class="box-title storeops-inline-135" ><i class="fa fa-flask"></i> {{ __('storeops::storeops.rules_ui.the_why_simulator') }}</h3>
                <p class="text-muted storeops-inline-134" >{{ __('storeops::storeops.rules_ui.test_how_multi_layer_policies_merge_in_the_real_world_see_exactl') }}</p>
            </div>
            <div class="box-body storeops-inline-133" >
                <form id="simulatorForm" class="form-inline">
                    <div class="form-group storeops-inline-132" >
                        <label  class="storeops-inline-131">{{ __('storeops::storeops.rules_ui.simulate_user_location') }}</label>
                        <select name="location_id" class="form-control storeops-inline-130" >
                            <option value="">{{ __('storeops::storeops.rules_ui.select_office_location') }}</option>
                            @foreach($locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group storeops-inline-129" >
                        <label  class="storeops-inline-128">{{ __('storeops::storeops.rules_ui.receiving_target') }}</label>
                        <select name="category_id" class="form-control storeops-inline-127" >
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
            <div id="simulation_results"  class="storeops-inline-126">
                <div class="text-center text-muted storeops-inline-125" >
                    <i class="fa fa-cogs fa-4x storeops-inline-124" ></i>
                    <h4>{{ __('storeops::storeops.rules_ui.select_a_context_and_click_run_simulation') }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('moar_scripts')
@include('storeops::admin.rules.partials.client-config', ['rulesPage' => 'simulator'])
@endsection
