@extends('layouts/default')
@section('title', __('storeops::rules.created'))

@section('content')
<style>
    .confirm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px 0 rgba(0,0,0,0.05); overflow: hidden; max-width: 650px; margin: 40px auto; text-align: center; padding: 40px; }
    .confirm-icon { color: #10b981; margin-bottom: 20px; }
    .confirm-title { font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 10px; }
    .confirm-sub { color: #64748b; font-size: 15px; margin-bottom: 30px; }
    
    .confirm-summary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 35px; text-align: left; }
    
    .confirm-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; }
    .action-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none !important; color: #475569; transition: all 0.15s; }
    .action-btn:hover { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; transform: translateY(-2px); }
    .action-btn i { font-size: 24px; margin-bottom: 10px; }
    .action-btn span { font-weight: bold; font-size: 14px; }
</style>

<div class="confirm-card">
    <div class="confirm-icon"><i class="fa fa-check-circle fa-5x"></i></div>
    <h2 class="confirm-title">{{ __('storeops::storeops.rules_ui.rule_draft_created_successfully') }}</h2>
    <p class="confirm-sub">{{ __('storeops::rules.draft_created_help') }}</p>

    <div class="confirm-summary">
        <h5 style="margin-top:0; font-weight:bold; color:#475569; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">{{ __('storeops::storeops.rules_ui.policy_specifications') }}</h5>
        
        <p style="font-size:14px; margin-bottom:8px; margin-top:10px;">
            {{ __('storeops::rules.name') }}: <strong>{{ $policy->name }}</strong>
        </p>
        <p style="font-size:14px; margin-bottom:8px;">
            {{ __('storeops::rules.version') }}: <strong>v{{ $policy->version ?? '1.0' }}</strong>
        </p>
        <p style="font-size:14px; margin-bottom:0;">
            {{ __('storeops::rules.rule_count') }}: <strong>{{ $policy->capabilities->count() }}</strong>
        </p>
    </div>

    <h5 style="font-weight:bold; color:#475569; text-transform:uppercase; margin-bottom:15px; text-align:left;">{{ __('storeops::storeops.rules_ui.what_would_you_like_to_do_next') }}</h5>
    
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
@endsection