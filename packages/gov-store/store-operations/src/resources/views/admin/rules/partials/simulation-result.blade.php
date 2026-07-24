<style>
    .sim-container { display: flex; width: 100%; }
    .sim-left { flex: 0 0 50%; padding: 30px; background: #fff; border-right: 2px solid #e2e8f0; }
    .sim-right { flex: 0 0 50%; padding: 30px; background: #f8fafc; }
    .sim-header { font-weight: bold; color: #1e293b; font-size: 16px; margin-bottom: 20px; text-transform: uppercase; border-bottom: 2px solid #cbd5e1; padding-bottom: 10px; }
    
    .mock-row { display: flex; margin-bottom: 25px; align-items: center; position: relative; }
    .mock-input-group { flex: 1; background: #f1f5f9; border: 1px solid #cbd5e1; padding: 15px; border-radius: 6px; }
    .mock-label { font-weight: bold; font-size: 13px; color: #475569; display: block; margin-bottom: 5px; }
    .mock-field { background: #fff; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 4px; color: #94a3b8; width: 100%; font-family: monospace; }
    
    .why-box { background: #eff6ff; border: 1px solid #bfdbfe; border-left: 4px solid #3b82f6; padding: 12px 15px; border-radius: 4px; margin-bottom: 25px; min-height: 72px; display: flex; flex-direction: column; justify-content: center; }
    .why-title { font-weight: bold; color: #1e40af; font-size: 12px; text-transform: uppercase; margin-bottom: 4px; }
    .why-desc { color: #1e3a8a; font-size: 13px; margin: 0; }
</style>

<div class="sim-container">
    
    <!-- LEFT PANE: What the Storekeeper Sees -->
    <div class="sim-left">
        <div class="sim-header"><i class="fa fa-desktop"></i> Mock Storekeeper View</div>
        
        <!-- Standard Item Info -->
        <div class="mock-row" style="margin-bottom: 40px;">
            <div style="flex: 1;">
                <span class="mock-label">Item Being Received</span>
                <div style="font-size: 16px; font-weight: bold; color: #0f172a;">
                    <i class="fa fa-box text-blue"></i> Generic {{ $category->name }}
                </div>
            </div>
            <div style="width: 100px;">
                <span class="mock-label">Quantity</span>
                <div class="mock-field" style="text-align: center; color: #0f172a;">1</div>
            </div>
        </div>

        <!-- Dynamic Simulated UI Requirements -->
        @foreach($simulatedUI as $code => $data)
            <div class="mock-row">
                <div class="mock-input-group">
                    <span class="mock-label">{{ $data['name'] }} <span class="text-danger">*</span></span>
                    
                    @if($code === 'require_warranty')
                        <div class="mock-field">Default: {{ $data['config']['warranty_months'] ?? 12 }} Months</div>
                    @elseif($code === 'require_serial')
                        <div class="mock-field">[ Enter Unique Serial Number ]</div>
                    @else
                        <div class="mock-field">[ Required Input ]</div>
                    @endif
                </div>
            </div>
        @endforeach

        <hr style="border-top: 1px dashed #cbd5e1; margin: 30px 0;">

        <!-- Mock Post Button -->
        <div style="text-align: right;">
            <button class="btn btn-success btn-lg disabled" style="opacity: 0.7; width: 100%;"><i class="fa fa-lock"></i> Post to Ledger</button>
        </div>
    </div>

    <!-- RIGHT PANE: The "Why" Explanation -->
    <div class="sim-right">
        <div class="sim-header"><i class="fa fa-lightbulb-o"></i> Rule Explanation (The "Why")</div>
        
        <div style="margin-bottom: 40px; padding-top: 5px;">
            <p class="text-muted" style="font-size: 13px; margin: 0;">Standard fields always shown to the user.</p>
        </div>

        <!-- Dynamic Explanations -->
        @forelse($simulatedUI as $code => $data)
            <div class="why-box">
                <div class="why-title">⬅️ REQUIRED: {{ $data['name'] }}</div>
                <p class="why-desc">
                    Mandated by <strong>{{ $data['source'] }}</strong> 
                    <span class="label label-default" style="margin-left: 5px; font-size: 10px;">Scope: {{ $data['layer'] }}</span>
                </p>
                @if(!empty($data['config']))
                    <p style="margin: 4px 0 0 0; font-size: 12px; color: #475569;"><i class="fa fa-sliders"></i> Configured Rules applied.</p>
                @endif
            </div>
        @empty
            <div class="why-box" style="background: #f1f5f9; border-color: #cbd5e1; border-left-color: #94a3b8;">
                <p class="why-desc text-muted">No additional identification or receiving rules are enforced for this item.</p>
            </div>
        @endforelse

        <hr style="border-top: 1px dashed #cbd5e1; margin: 30px 0;">

        <!-- Backend Automations Explanation -->
        <h5 style="font-weight: bold; color: #475569; text-transform: uppercase;">Background Automations</h5>
        @forelse($automations as $code => $data)
            <div style="margin-bottom: 15px; padding-left: 15px; border-left: 2px solid #10b981;">
                <strong style="color: #065f46;">{{ $data['name'] }}</strong>
                <p style="margin: 2px 0 0 0; font-size: 12px; color: #475569;">Will execute automatically on post. <br>(Mandated by: {{ $data['source'] }})</p>
            </div>
        @empty
            <p class="text-muted" style="font-size: 12px;">No specific automations are tied to this item.</p>
        @endforelse

    </div>
</div>