@extends('layouts/default')
@section('title', 'Policy Builder Canvas')

@section('content')
<style>
    .builder-header { background: #fff; padding: 20px; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 20px; }
    .rule-group { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 20px; overflow: hidden; }
    .rule-group-header { background: #f8fafc; padding: 12px 20px; font-weight: bold; color: #475569; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
    .rule-row { padding: 20px; border-bottom: 1px dashed #e2e8f0; display: flex; flex-wrap: wrap; align-items: flex-start; }
    .rule-row:last-child { border-bottom: none; }
    
    .rule-info { flex: 1; min-width: 300px; padding-right: 20px; }
    .rule-title { font-size: 15px; font-weight: bold; color: #1e293b; margin-bottom: 5px; }
    .rule-desc { font-size: 13px; color: #64748b; }
    
    .rule-controls { flex: 0 0 350px; }
    
    /* Traffic Light Toggle Styles */
    .behavior-toggle { display: flex; background: #f1f5f9; border-radius: 6px; overflow: hidden; border: 1px solid #cbd5e1; }
    .behavior-toggle label { flex: 1; text-align: center; padding: 8px 10px; margin: 0; cursor: pointer; font-size: 13px; font-weight: 600; color: #64748b; transition: all 0.2s; border-right: 1px solid #cbd5e1; }
    .behavior-toggle label:last-child { border-right: none; }
    .behavior-toggle input { display: none; }
    
    /* Selected States */
    .behavior-toggle input[value="ENFORCE"]:checked + span { color: #059669; }
    .behavior-toggle label.state-enforce:has(input:checked) { background: #d1fae5; }
    
    .behavior-toggle input[value="INHERIT"]:checked + span { color: #475569; }
    .behavior-toggle label.state-inherit:has(input:checked) { background: #e2e8f0; }
    
    .behavior-toggle input[value="DISABLE"]:checked + span { color: #dc2626; }
    .behavior-toggle label.state-disable:has(input:checked) { background: #fee2e2; }

    /* Configuration Panel */
    .config-panel { width: 100%; margin-top: 15px; background: #f8fafc; padding: 15px; border-left: 4px solid #10b981; border-radius: 0 4px 4px 0; display: none; }
    .config-panel.active { display: block; }
</style>

<div class="row">
    <div class="col-md-10 col-md-offset-1">
        
        <div class="builder-header">
            <h3 style="margin-top: 0; color: #1e293b;"><i class="fa fa-pencil-square-o"></i> Editing Policy: <strong>{{ $policy->name }}</strong></h3>
            <span class="label label-warning" style="font-size: 12px;"><i class="fa fa-file-text-o"></i> DRAFT (v{{ $policy->version ?? '1.0' }})</span>
            <span class="label label-default" style="font-size: 12px; margin-left: 10px;">Scope: {{ $policy->scope ?? 'Global' }}</span>
            <p style="margin-top: 10px; color: #64748b;">Set the business rules for this policy. Rules set to "Inherit" will defer to broader parent policies.</p>
        </div>

        <form action="{{ route('storeops.admin.rules.policies.draft', $policy->id) }}" method="POST">
            @csrf
            
            @foreach($groupedRules as $groupName => $rules)
                <div class="rule-group">
                    <div class="rule-group-header">
                        {{ $groupName }}
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
                                        <span>🟢 Enforce</span>
                                    </label>
                                    <label class="state-inherit">
                                        <input type="radio" name="rules[{{ $code }}][behavior]" value="INHERIT" class="behavior-radio" {{ $behavior === 'INHERIT' ? 'checked' : '' }}>
                                        <span>⚪ Inherit</span>
                                    </label>
                                    <label class="state-disable">
                                        <input type="radio" name="rules[{{ $code }}][behavior]" value="DISABLE" class="behavior-radio" {{ $behavior === 'DISABLE' ? 'checked' : '' }}>
                                        <span>🔴 Disable</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Dynamic Configuration Panel (Only shows if ENFORCED) -->
                            <!-- Note: In a full system, you might loop through $dictInfo['requirements'] here. We hardcode specific configs based on code for MVP UI. -->
                            @if($code === 'require_warranty')
                                <div class="config-panel {{ $behavior === 'ENFORCE' ? 'active' : '' }}" data-code="{{ $code }}">
                                    <label>Default Warranty Period (Months)</label>
                                    <div class="input-group" style="width: 200px;">
                                        <input type="number" name="rules[{{ $code }}][config][warranty_months]" class="form-control input-sm" value="{{ $config['warranty_months'] ?? 12 }}" min="0">
                                        <span class="input-group-addon">Months</span>
                                    </div>
                                </div>
                            @elseif($code === 'require_serial')
                                <div class="config-panel {{ $behavior === 'ENFORCE' ? 'active' : '' }}" data-code="{{ $code }}">
                                    <label class="checkbox-inline">
                                        <input type="checkbox" name="rules[{{ $code }}][config][allow_auto_gen]" value="1" {{ isset($config['allow_auto_gen']) && $config['allow_auto_gen'] ? 'checked' : '' }}>
                                        Allow System to Auto-Generate if Missing
                                    </label>
                                </div>
                            @endif

                        </div>
                    @endforeach
                </div>
            @endforeach

        <div class="box-footer text-right" style="background: transparent; border-top: 1px solid #e2e8f0; padding-top: 20px;">
    <a href="{{ route('storeops.admin.rules.index') }}" class="btn btn-default">Cancel</a>
    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Save Draft</button>
    <button type="button" class="btn btn-primary" id="btn_trigger_publish"><i class="fa fa-rocket"></i> Validate & Publish</button>
</div>
        </form>
    </div>
</div>
<!-- IMPACT ANALYSIS & PUBLISHING MODAL -->
<div class="modal fade" id="publishModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-radius: 6px; overflow: hidden;">
            <div class="modal-header bg-primary" style="background: #3c8dbc; color: #fff;">
                <h4 class="modal-title" style="font-weight: bold;"><i class="fa fa-warning"></i> Confirm Policy Publication</h4>
            </div>
            
            <div class="modal-body" style="padding: 25px;">
                <p class="lead" style="color: #1e293b; margin-bottom: 20px;">
                    You are about to promote this draft to the active, live standard.
                </p>
                
                <div class="well" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 20px; border-radius: 4px;">
                    <h5 style="margin-top:0; font-weight: bold; color: #334155; text-transform: uppercase;">Estimated Blast Radius:</h5>
                    
                    <p style="font-size: 14px; margin-bottom: 8px;">
                        🎯 <strong><span id="impact_categories">0</span> Product Categories</strong> will be affected.
                    </p>
                    <p style="font-size: 14px; margin-bottom: 0;">
                        📄 <strong><span id="impact_drafts">0</span> Open Draft Receipts</strong> currently contain these items.
                    </p>
                </div>

                <div class="alert" id="risk_alert_panel" style="display:none; padding: 15px; border-radius: 4px; font-size:13px;">
                    <i class="fa fa-info-circle"></i> <strong>Operation Warning:</strong><br>
                    <span id="risk_desc"></span>
                </div>

                <p class="text-danger" style="font-size: 12px; margin-top: 20px;">
                    <i class="fa fa-shield"></i> <strong>Audit Integrity Assurance:</strong> Historically posted documents are completely safe and will not be altered, protecting previous financial ledger audits.
                </p>
            </div>

            <div class="modal-footer" style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 15px 25px;">
                <form action="{{ route('storeops.admin.rules.policies.publish', $policy->id) }}" method="POST">
                    @csrf
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn_confirm_publish" style="font-weight: bold;">
                        Publish & Apply Rules
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    // Listen for changes on the traffic light radios
    $('.behavior-radio').change(function() {
        let val = $(this).val();
        let $row = $(this).closest('.rule-row');
        let $configPanel = $row.find('.config-panel');
        
        // Show configuration fields ONLY if the rule is Enforced
        if (val === 'ENFORCE') {
            $configPanel.slideDown(200).addClass('active');
        } else {
            $configPanel.slideUp(200).removeClass('active');
        }
    });
});

// Trigger Impact Analysis and Publish Modal
    $('#btn_trigger_publish').click(function() {
        let btn = $(this);
        btn.html('<i class="fa fa-spinner fa-spin"></i> Analyzing...').attr('disabled', 'disabled');

        $.get('{{ route("storeops.admin.rules.policies.impact", $policy->id) }}')
            .done(function(data) {
                btn.html('<i class="fa fa-rocket"></i> Validate & Publish').removeAttr('disabled');

                // Fill impact data
                $('#impact_categories').text(data.categories_affected);
                $('#impact_drafts').text(data.drafts_affected);

                // Configure risk warnings dynamically
                let alertPanel = $('#risk_alert_panel');
                let riskDesc = $('#risk_desc');

                if (data.risk_level === 'HIGH' || data.risk_level === 'MEDIUM') {
                    alertPanel.removeClass('alert-info alert-warning alert-danger')
                               .addClass(data.risk_level === 'HIGH' ? 'alert-danger' : 'alert-warning')
                               .show();
                    riskDesc.html(`Storekeepers currently editing those ${data.drafts_affected} draft receipts will instantly see the new validations (e.g., Serial numbers, Expiries) the next time they attempt to save.`);
                } else {
                    alertPanel.hide();
                }

                // Show the modal
                $('#publishModal').modal('show');
            })
            .fail(function() {
                alert('Analysis failed. Please save the policy draft first.');
                btn.html('<i class="fa fa-rocket"></i> Validate & Publish').removeAttr('disabled');
            });
    });
</script>
@endsection