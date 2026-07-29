@extends('layouts/default')
@section('title', 'Create Business Rule')

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

<div class="wizard-card">
    <div class="wizard-header">
        <h3 style="margin: 0; font-weight: 800; color: #0f172a;"><i class="fa fa-plus-circle text-blue"></i> Create Business Rule</h3>
        <p class="text-muted" style="margin: 5px 0 0 0; font-size: 13px;">Using Template: <strong>{{ ucfirst($template) }} Standard</strong></p>
    </div>

    <form action="{{ route('storeops.admin.rules.policies.store') }}" method="POST" id="wizardForm">
        @csrf
        <input type="hidden" name="template" value="{{ $template }}">

        <div class="wizard-body">
            <!-- PROGRESS STEP BAR -->
            <div class="progress-steps">
                <span class="step-node active" id="badge_step_1">1. Choose Template</span>
                <span class="step-node" id="badge_step_2">2. Identity Details</span>
                <span class="step-node" id="badge_step_3">3. Review Rules</span>
            </div>

            <!-- STEP 1: SELECT BASES (Pre-selected, informational for Step 1) -->
            <div class="wizard-step-pane active" id="pane_step_1">
                <h4 style="font-weight: bold; color: #1e293b; margin-top:0; margin-bottom: 15px;">Starting Template Verified</h4>
                <p style="color: #64748b; line-height: 1.5; font-size:14px;">
                    You are starting with the **{{ ucfirst($template) }}** configuration baseline. This is highly recommended to quickly scaffold standard governances for government stores.
                </p>
                <div class="well well-sm" style="background:#f8fafc; border-color:#e2e8f0; margin-top:20px;">
                    <small class="text-muted"><i class="fa fa-info-circle text-blue"></i> On Step 3 you can preview exactly what rules are included inside this baseline template.</small>
                </div>
            </div>

            <!-- STEP 2: NAME & VALUE IDENTITY -->
            <div class="wizard-step-pane" id="pane_step_2">
                <h4 style="font-weight: bold; color: #1e293b; margin-top:0; margin-bottom: 20px;">Provide Rule Details</h4>
                
                <div class="form-group">
                    <label style="color:#475569; font-weight:bold; margin-bottom: 8px;">1. Rule Name:</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Dhaka HQ Hardware Policy" style="height: 40px; border-radius: 4px;" id="input_rule_name">
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label style="color:#475569; font-weight:bold; margin-bottom: 8px;">2. Description:</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Briefly describe what this policy is governing..." style="border-radius:4px;"></textarea>
                </div>
            </div>

            <!-- STEP 3: PREVIEW RULES TABLE -->
            <div class="wizard-step-pane" id="pane_step_3">
                <h4 style="font-weight: bold; color: #1e293b; margin-top:0; margin-bottom: 15px;">Review Pre-Configured Behaviors</h4>
                <p style="color: #64748b; font-size:13.5px; margin-bottom: 20px;">These baseline requirements will be seeded in your new draft. You can customize them at any time in the builder.</p>
                
                <table class="table table-bordered review-table">
                    <thead>
                        <tr>
                            <th>Enforced Business Rule</th>
                            <th class="text-right">Seeded Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($previewRules as $ruleName => $status)
                        <tr>
                            <td><strong>{{ $ruleName }}</strong></td>
                            <td class="text-right"><span style="font-size:12.5px; font-weight:600;">{{ $status }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wizard-footer">
            <button type="button" class="btn btn-default" id="btn_prev" style="visibility: hidden;">⬅ Back</button>
            <button type="button" class="btn btn-primary" id="btn_next">Next Step ➔</button>
            <button type="submit" class="btn btn-success" id="btn_submit" style="display: none;"><i class="fa fa-check-circle"></i> Create Rule Draft</button>
        </div>
    </form>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    let currentStep = 1;

    $('#btn_next').click(function() {
        if (currentStep === 1) {
            transitionToStep(2);
        } else if (currentStep === 2) {
            // Basic validation check
            let nameVal = $('#input_rule_name').val().trim();
            if (nameVal === '') {
                alert('Please provide a unique name for your new business rule.');
                return;
            }
            transitionToStep(3);
        }
    });

    $('#btn_prev').click(function() {
        if (currentStep === 3) {
            transitionToStep(2);
        } else if (currentStep === 2) {
            transitionToStep(1);
        }
    });

    function transitionToStep(step) {
        currentStep = step;

        // Hide all steps
        $('.wizard-step-pane').removeClass('active');
        $('#pane_step_' + step).addClass('active');

        // Toggle badge nodes
        $('.step-node').removeClass('active complete');
        for (let i = 1; i <= 3; i++) {
            if (i < step) {
                $('#badge_step_' + i).addClass('complete');
            } else if (i === step) {
                $('#badge_step_' + i).addClass('active');
            }
        }

        // Toggle back buttons
        if (step === 1) {
            $('#btn_prev').css('visibility', 'hidden');
        } else {
            $('#btn_prev').css('visibility', 'visible');
        }

        // Toggle submit/next buttons
        if (step === 3) {
            $('#btn_next').hide();
            $('#btn_submit').show();
        } else {
            $('#btn_next').show();
            $('#btn_submit').hide();
        }
    }
});
</script>
@endsection