@extends('layouts/default')
@section('title', __('storeops::rules.create'))

@section('content')
<style>
    .wizard-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05); overflow: hidden; max-width: 700px; margin: 30px auto; }
    .wizard-header { background: #f8fafc; padding: 25px; border-bottom: 1px solid #e2e8f0; }
    .wizard-body { padding: 30px; }
    .wizard-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 30px; display: flex; justify-content: space-between; }
    
    /* Progress Indicator */
    .progress-steps { display: flex; justify-content: space-between; margin-bottom: 25px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; }
    .step-node { font-size: 13px; font-weight: bold; color: #94a3b8; }
    .step-node.active { color: #3b82f6; }
    .step-node.complete { color: #10b981; }

    .wizard-step-pane { display: none; }
    .wizard-step-pane.active { display: block; }

    /* Review Table */
    .review-table th { background: #f8fafc; text-transform: uppercase; font-size: 11px; color: #64748b; }
</style>
<link rel="stylesheet" href="{{ url('css/dist/store-operations.css') }}">

<div class="wizard-card">
    <div class="wizard-header">
        <h3 style="margin: 0; font-weight: 800; color: #0f172a;"><i class="fa fa-plus-circle text-blue"></i> {{ __('storeops::storeops.rules_ui.create_business_rule') }}</h3>
        <p class="text-muted" style="margin: 5px 0 0 0; font-size: 13px;">{{ __('storeops::storeops.rules_ui.using_template') }} <strong>{{ ucfirst($template) }} Standard</strong></p>
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
                <h4 style="font-weight: bold; color: #1e293b; margin-top:0; margin-bottom: 15px;">{{ __('storeops::storeops.rules_ui.starting_template_verified') }}</h4>
                <p style="color: #64748b; line-height: 1.5; font-size:14px;">
                    {{ __('storeops::rules.template_help') }}
                </p>
                <div class="well well-sm" style="background:#f8fafc; border-color:#e2e8f0; margin-top:20px;">
                    <small class="text-muted"><i class="fa fa-info-circle text-blue"></i> {{ __('storeops::storeops.rules_ui.on_step_3_you_can_preview_exactly_what_rules_are_included_inside') }}</small>
                </div>
            </div>

            <!-- STEP 2: NAME & VALUE IDENTITY -->
            <div class="wizard-step-pane" id="pane_step_2">
                <h4 style="font-weight: bold; color: #1e293b; margin-top:0; margin-bottom: 20px;">{{ __('storeops::storeops.rules_ui.provide_rule_details') }}</h4>
                
                <div class="form-group">
                    <label style="color:#475569; font-weight:bold; margin-bottom: 8px;">{{ __('storeops::storeops.rules_ui.1_rule_name') }}</label>
                    <input type="text" name="name" class="form-control" required placeholder="{{ __('storeops::rules.name_placeholder') }}" style="height: 40px; border-radius: 4px;" id="input_rule_name">
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label style="color:#475569; font-weight:bold; margin-bottom: 8px;">{{ __('storeops::storeops.rules_ui.2_description') }}</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="{{ __('storeops::rules.description_placeholder') }}" style="border-radius:4px;"></textarea>
                </div>
            </div>

            <!-- STEP 3: PREVIEW RULES TABLE -->
            <div class="wizard-step-pane" id="pane_step_3">
                <h4 style="font-weight: bold; color: #1e293b; margin-top:0; margin-bottom: 15px;">{{ __('storeops::storeops.rules_ui.review_pre_configured_behaviors') }}</h4>
                <p style="color: #64748b; font-size:13.5px; margin-bottom: 20px;">{{ __('storeops::storeops.rules_ui.these_baseline_requirements_will_be_seeded_in_your_new_draft_you') }}</p>
                
                <table class="table table-bordered review-table">
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
                            <td class="text-right"><span style="font-size:12.5px; font-weight:600;">{{ __('storeops::rules.'.$status) }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wizard-footer">
            <button type="button" class="btn btn-default" id="btn_prev" style="visibility: hidden;">{{ __('storeops::storeops.rules_ui.back') }}</button>
            <button type="button" class="btn btn-primary" id="btn_next">{{ __('storeops::storeops.rules_ui.next_step') }}</button>
            <button type="submit" class="btn btn-success" id="btn_submit" style="display: none;"><i class="fa fa-check-circle"></i> {{ __('storeops::storeops.rules_ui.create_rule_draft') }}</button>
        </div>
    </form>
</div>
@endsection

@section('moar_scripts')
@include('storeops::admin.rules.partials.client-config', ['rulesPage' => 'create'])
@endsection
