@extends('layouts/default')
@section('title', 'Rule Draft Created')

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
    <h2 class="confirm-title">Rule Draft Created Successfully</h2>
    <p class="confirm-sub">Your new policy file is safely generated in <strong>DRAFT</strong> status.</p>

    <div class="confirm-summary">
        <h5 style="margin-top:0; font-weight:bold; color:#475569; text-transform:uppercase; letter-spacing:0.5px; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">Policy Specifications</h5>
        
        <p style="font-size:14px; margin-bottom:8px; margin-top:10px;">
            📝 Name: <strong>{{ $policy->name }}</strong>
        </p>
        <p style="font-size:14px; margin-bottom:8px;">
            ⚙️ Version: <strong>v{{ $policy->version ?? '1.0' }}</strong>
        </p>
        <p style="font-size:14px; margin-bottom:0;">
            🛠️ Seeded Rules: <strong>{{ $policy->capabilities->count() }} active configurations</strong>
        </p>
    </div>

    <h5 style="font-weight:bold; color:#475569; text-transform:uppercase; margin-bottom:15px; text-align:left;">What would you like to do next?</h5>
    
    <div class="confirm-actions">
        <!-- CUSTOMIZE (PHASE 4 canvas) -->
        <a href="{{ route('storeops.admin.rules.policies.edit', $policy->id) }}" class="action-btn">
            <i class="fa fa-sliders text-blue"></i>
            <span>Customize Rules</span>
        </a>

        <!-- DEPLOY/ASSIGN (PHASE 3 matrix) -->
        <a href="{{ route('storeops.admin.rules.index') }}" class="action-btn">
            <i class="fa fa-plus-circle text-green"></i>
            <span>Deploy/Assign</span>
        </a>

        <!-- BACK TO LIBRARY -->
        <a href="{{ route('storeops.admin.rules.index') }}" class="action-btn">
            <i class="fa fa-cubes text-muted"></i>
            <span>Return to GPO Hub</span>
        </a>
    </div>
</div>
@endsection