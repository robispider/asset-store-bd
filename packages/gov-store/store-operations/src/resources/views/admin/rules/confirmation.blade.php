@extends('layouts/default')
@section('title', __('storeops::rules.created'))

@section('content')
<div class="storeops-theme">


<div class="confirm-card">
    <div class="confirm-icon"><i class="fa fa-check-circle fa-5x"></i></div>
    <h2 class="confirm-title">{{ __('storeops::storeops.rules_ui.rule_draft_created_successfully') }}</h2>
    <p class="confirm-sub">{{ __('storeops::rules.draft_created_help') }}</p>

    <div class="confirm-summary">
        <h5  class="storeops-inline-5">{{ __('storeops::storeops.rules_ui.policy_specifications') }}</h5>

        <p  class="storeops-inline-4">
            {{ __('storeops::rules.name') }}: <strong>{{ $policy->name }}</strong>
        </p>
        <p  class="storeops-inline-3">
            {{ __('storeops::rules.version') }}: <strong>v{{ $policy->version ?? '1.0' }}</strong>
        </p>
        <p  class="storeops-inline-2">
            {{ __('storeops::rules.rule_count') }}: <strong>{{ $policy->capabilities->count() }}</strong>
        </p>
    </div>

    <h5  class="storeops-inline-1">{{ __('storeops::storeops.rules_ui.what_would_you_like_to_do_next') }}</h5>

    <div class="confirm-actions">
        <!-- CUSTOMIZE (PHASE 4 canvas) -->
        <a href="{{ route('storeops.admin.rules.policies.edit', $policy->id) }}" class="action-btn">
            <i class="fa fa-sliders text-blue"></i>
            <span>{{ __('storeops::storeops.rules_ui.customize_rules') }}</span>
        </a>

        <!-- DEPLOY/ASSIGN (PHASE 3 matrix) -->
        <a href="{{ route('storeops.admin.rules.index') }}" class="action-btn">
            <i class="fa fa-plus-circle text-green"></i>
            <span>{{ __('storeops::storeops.rules_ui.deploy_assign') }}</span>
        </a>

        <!-- BACK TO LIBRARY -->
        <a href="{{ route('storeops.admin.rules.index') }}" class="action-btn">
            <i class="fa fa-cubes text-muted"></i>
            <span>{{ __('storeops::storeops.rules_ui.return_to_gpo_hub') }}</span>
        </a>
    </div>
</div>
</div>
@endsection