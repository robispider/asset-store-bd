@extends('layouts/default')
@section('title', __('storeops::rules.create'))

@section('content')
<div class="storeops-theme">

<link rel="stylesheet" href="{{ url('css/dist/store-operations.css') }}">

<div class="wizard-card">
    <div class="wizard-header">
        <h3  class="storeops-inline-21"><i class="fa fa-plus-circle text-blue"></i> {{ __('storeops::storeops.rules_ui.create_business_rule') }}</h3>
        <p class="text-muted storeops-inline-20" >{{ __('storeops::storeops.rules_ui.using_template') }} <strong>{{ ucfirst($template) }} Standard</strong></p>
    </div>

    <form action="{{ route('storeops.admin.rules.policies.store') }}" method="POST" id="wizardForm">
        @csrf
        <input type="hidden" name="template" value="{{ $template }}">

        <div class="wizard-body">
            <!-- PROGRESS STEP BAR -->
            <div class="progress-steps">
                <span class="step-node active" id="badge_step_1">{{ __('storeops::storeops.rules_ui.1_choose_template') }}</span>
                <span class="step-node" id="badge_step_2">{{ __('storeops::storeops.rules_ui.2_identity_details') }}</span>
                <span class="step-node" id="badge_step_3">{{ __('storeops::storeops.rules_ui.3_review_rules') }}</span>
            </div>

            <!-- STEP 1: SELECT BASES (Pre-selected, informational for Step 1) -->
            <div class="wizard-step-pane active" id="pane_step_1">
                <h4  class="storeops-inline-19">{{ __('storeops::storeops.rules_ui.starting_template_verified') }}</h4>
                <p  class="storeops-inline-18">
                    {{ __('storeops::rules.template_help') }}
                </p>
                <div class="well well-sm storeops-inline-17" >
                    <small class="text-muted"><i class="fa fa-info-circle text-blue"></i> {{ __('storeops::storeops.rules_ui.on_step_3_you_can_preview_exactly_what_rules_are_included_inside') }}</small>
                </div>
            </div>

            <!-- STEP 2: NAME & VALUE IDENTITY -->
            <div class="wizard-step-pane" id="pane_step_2">
                <h4  class="storeops-inline-16">{{ __('storeops::storeops.rules_ui.provide_rule_details') }}</h4>

                <div class="form-group">
                    <label  class="storeops-inline-15">{{ __('storeops::storeops.rules_ui.1_rule_name') }}</label>
                    <input type="text" name="name" class="form-control storeops-inline-14" required placeholder="{{ __('storeops::rules.name_placeholder') }}"  id="input_rule_name">
                </div>

                <div class="form-group storeops-inline-13" >
                    <label  class="storeops-inline-12">{{ __('storeops::storeops.rules_ui.2_description') }}</label>
                    <textarea name="description" class="form-control storeops-inline-11" rows="3" placeholder="{{ __('storeops::rules.description_placeholder') }}" ></textarea>
                </div>
            </div>

            <!-- STEP 3: PREVIEW RULES TABLE -->
            <div class="wizard-step-pane" id="pane_step_3">
                <h4  class="storeops-inline-10">{{ __('storeops::storeops.rules_ui.review_pre_configured_behaviors') }}</h4>
                <p  class="storeops-inline-9">{{ __('storeops::storeops.rules_ui.these_baseline_requirements_will_be_seeded_in_your_new_draft_you') }}</p>

                <table class="table table-bordered review-table gs-table">
                    <thead>
                        <tr>
                            <th>{{ __('storeops::storeops.rules_ui.enforced_business_rule') }}</th>
                            <th class="text-right">{{ __('storeops::storeops.rules_ui.seeded_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($previewRules as $ruleName => $status)
                        <tr>
                            <td><strong>{{ __('storeops::rules.'.$ruleName.'_name') }}</strong></td>
                            <td class="text-right"><span  class="storeops-inline-8">{{ __('storeops::rules.'.$status) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wizard-footer">
            <button type="button" class="btn btn-default storeops-inline-7" id="btn_prev" >{{ __('storeops::storeops.rules_ui.back') }}</button>
            <button type="button" class="btn btn-primary" id="btn_next">{{ __('storeops::storeops.rules_ui.next_step') }}</button>
            <button type="submit" class="btn btn-success storeops-inline-6" id="btn_submit" ><i class="fa fa-check-circle"></i> {{ __('storeops::storeops.rules_ui.create_rule_draft') }}</button>
        </div>
    </form>
</div>
</div>
@endsection

@section('moar_scripts')
@include('storeops::admin.rules.partials.client-config', ['rulesPage' => 'create'])
@endsection
