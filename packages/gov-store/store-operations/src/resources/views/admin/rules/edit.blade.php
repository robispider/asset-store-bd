@extends('layouts/default')
@section('title', __('storeops::rules.builder'))

@section('content')
<div class="storeops-theme">
@if($policy->published_by)
<div class="alert alert-info">{{ __('tenantops::access.published') }}: {{ $policy->published_by }} — {{ $policy->published_at }} — {{ $policy->publish_reason }}</div>
@endif

<link rel="stylesheet" href="{{ url('css/dist/store-operations.css') }}">

<div class="row">
    <div class="col-md-10 col-md-offset-1">

        <div class="builder-header">
            <h3  class="storeops-inline-40"><i class="fa fa-pencil-square-o"></i> {{ __('storeops::storeops.rules_ui.editing_policy') }} <strong>{{ $policy->name }}</strong></h3>
            <span class="label label-warning storeops-inline-39" ><i class="fa fa-file-text-o"></i> {{ __('storeops::storeops.draft') }} (v{{ $policy->version ?? '1.0' }})</span>
            <span class="label label-default storeops-inline-38" >Scope: {{ $policy->scope ?? 'Global' }}</span>
            <p  class="storeops-inline-37">{{ __('storeops::storeops.rules_ui.set_the_business_rules_for_this_policy_rules_set_to_inherit_will') }}</p>
        </div>

        <form action="{{ route('storeops.admin.rules.policies.draft', $policy->id) }}" method="POST">
            @csrf

            @foreach($groupedRules as $groupName => $rules)
                <div class="rule-group">
                    <div class="rule-group-header">
                        {{ __('storeops::rules.'.strtolower(str_replace(' ', '_', $groupName))) }}
                    </div>

                    @foreach($rules as $code => $dictInfo)
                        @php
                            $existing = $existingCaps->get($code);
                            $behavior = $existing ? $existing->behavior->value : 'INHERIT';
                            $config = $existing ? $existing->config_payload : [];
                        @endphp

                        <div class="rule-row">
                            <div class="rule-info">
                                <div class="rule-title">{{ $dictInfo['name'] }}</div>
                                <div class="rule-desc">{{ $dictInfo['desc'] }}</div>
                            </div>

                            <div class="rule-controls">
                                <div class="behavior-toggle">
                                    <label class="state-enforce">
                                        <input type="radio" name="rules[{{ $code }}][behavior]" value="ENFORCE" class="behavior-radio" {{ $behavior === 'ENFORCE' ? 'checked' : '' }}>
                                        <span>{{ __('storeops::storeops.rules_ui.enforce') }}</span>
                                    </label>
                                    <label class="state-inherit">
                                        <input type="radio" name="rules[{{ $code }}][behavior]" value="INHERIT" class="behavior-radio" {{ $behavior === 'INHERIT' ? 'checked' : '' }}>
                                        <span>{{ __('storeops::storeops.rules_ui.inherit') }}</span>
                                    </label>
                                    <label class="state-disable">
                                        <input type="radio" name="rules[{{ $code }}][behavior]" value="DISABLE" class="behavior-radio" {{ $behavior === 'DISABLE' ? 'checked' : '' }}>
                                        <span>{{ __('storeops::storeops.rules_ui.disable') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Dynamic Configuration Panel (Only shows if ENFORCED) -->
                            <!-- Note: In a full system, you might loop through $dictInfo['requirements'] here. We hardcode specific configs based on code for MVP UI. -->
                            @if($code === 'require_warranty')
                                <div class="config-panel {{ $behavior === 'ENFORCE' ? 'active' : '' }}" data-code="{{ $code }}">
                                    <label>{{ __('storeops::storeops.rules_ui.default_warranty_period_months') }}</label>
                                    <div class="input-group storeops-inline-36" >
                                        <input type="number" name="rules[{{ $code }}][config][warranty_months]" class="form-control input-sm" value="{{ $config['warranty_months'] ?? 12 }}" min="0">
                                        <span class="input-group-addon">{{ __('storeops::storeops.rules_ui.months') }}</span>
                                    </div>
                                </div>
                            @elseif($code === 'require_serial')
                                <div class="config-panel {{ $behavior === 'ENFORCE' ? 'active' : '' }}" data-code="{{ $code }}">
                                    <p>{{ __('storeops::rules.serial_policy_help') }}</p>
                                </div>
                            @endif

                        </div>
                    @endforeach
                </div>
            @endforeach

        <div class="box-footer text-right storeops-inline-35" >
    <a href="{{ route('storeops.admin.rules.index') }}" class="btn btn-default">{{ __('storeops::storeops.rules_ui.cancel') }}</a>
    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> {{ __('storeops::storeops.rules_ui.save_draft') }}</button>
    <button type="button" class="btn btn-primary" id="btn_trigger_publish"><i class="fa fa-rocket"></i> {{ __('storeops::storeops.rules_ui.validate_publish') }}</button>
</div>
        </form>
    </div>
</div>
<!-- IMPACT ANALYSIS & PUBLISHING MODAL -->
<div class="modal fade" id="publishModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content storeops-inline-34" >
            <div class="modal-header bg-primary storeops-inline-33" >
                <h4 class="modal-title storeops-inline-32" ><i class="fa fa-warning"></i> {{ __('storeops::storeops.rules_ui.confirm_policy_publication') }}</h4>
            </div>

            <div class="modal-body storeops-inline-31" >
                <p class="lead storeops-inline-30" >
                    {{ __('storeops::storeops.rules_ui.you_are_about_to_promote_this_draft_to_the_active_live_standard') }}
                </p>

                <div class="well storeops-inline-29" >
                    <h5  class="storeops-inline-28">{{ __('storeops::storeops.rules_ui.estimated_blast_radius') }}</h5>

                    <p  class="storeops-inline-27">
                        🎯 <strong><span id="impact_categories">0</span> {{ __('storeops::storeops.rules_ui.product_categories') }}</strong> {{ __('storeops::storeops.rules_ui.will_be_affected') }}
                    </p>
                    <p  class="storeops-inline-26">
                        📄 <strong><span id="impact_drafts">0</span> {{ __('storeops::storeops.rules_ui.open_draft_receipts') }}</strong> {{ __('storeops::storeops.rules_ui.currently_contain_these_items') }}
                    </p>
                </div>

                <div class="alert storeops-inline-25" id="risk_alert_panel" >
                    <i class="fa fa-info-circle"></i> <strong>{{ __('storeops::storeops.rules_ui.operation_warning') }}</strong><br>
                    <span id="risk_desc"></span>
                </div>

                <p class="text-danger storeops-inline-24" >
                    <i class="fa fa-shield"></i> <strong>{{ __('storeops::storeops.rules_ui.audit_integrity_assurance') }}</strong> {{ __('storeops::storeops.rules_ui.historically_posted_documents_are_completely_safe_and_will_not_b') }}
                </p>
            </div>

            <div class="modal-footer storeops-inline-23" >
                <form action="{{ route('storeops.admin.rules.policies.publish', $policy->id) }}" method="POST">
                    @csrf
                    <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('storeops::storeops.rules_ui.cancel') }}</button>
                    <button type="submit" class="btn btn-primary storeops-inline-22" id="btn_confirm_publish" >
                        {{ __('storeops::storeops.rules_ui.publish_apply_rules') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@section('moar_scripts')
@include('storeops::admin.rules.partials.client-config', ['rulesPage' => 'edit'])
@endsection
