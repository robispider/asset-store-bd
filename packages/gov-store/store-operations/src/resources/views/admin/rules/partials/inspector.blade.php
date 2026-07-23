<div style="margin-bottom: 25px;">
    <h2 style="margin: 0 0 5px 0; color: #333; font-weight: bold;">{{ $category->name }}</h2>
    <span class="text-muted" style="font-size: 14px;">
        Snippet-IT Inventory Category &bull; {{ ucfirst($category->category_type) }}
    </span>
</div>

@if(!$assignment)
    <div class="alert alert-warning">
        <h4><i class="icon fas fa-exclamation-triangle"></i> No Rules Assigned</h4>
        This category does not have an active policy assigned to it. It cannot be processed through the Gov-Store receiving workflow.
    </div>
@else
    <div style="background: #fdfdfd; border: 1px solid #ddd; border-radius: 4px; padding: 20px; margin-bottom: 30px;">
        <h4 style="margin-top:0; color:#444; border-bottom: 1px solid #eee; padding-bottom: 10px;">
            Active Policy: <strong class="text-blue">{{ $assignment->profile->name }}</strong>
        </h4>
        <p class="text-muted" style="font-size: 12px; margin-bottom: 20px;">
            Effective since: {{ $assignment->effective_from->format('d M Y') }} &bull; Scope: {{ $assignment->profile->scope }}
        </p>

        <!-- Current Behavior Read-Only List -->
        <h5 style="font-weight:bold; color:#333; margin-bottom: 15px;">CURRENT BEHAVIORS ENFORCED:</h5>
        <div class="row">
            @php
                // Extract active capability codes from the assigned policy
                $activeCaps = $assignment->profile->capabilities->pluck('capability_code')->toArray();
            @endphp
            
            @foreach($systemCapabilities as $code => $details)
                <div class="col-md-6" style="margin-bottom: 12px;">
                    @if(in_array($code, $activeCaps))
                        <span class="text-green" style="font-size: 14px;"><i class="fas fa-check-circle"></i> {{ $details['label'] }}</span><br>
                        <small class="text-muted" style="margin-left: 20px;">Group: {{ $details['group'] }}</small>
                    @else
                        <span class="text-muted" style="opacity: 0.5;"><i class="far fa-circle"></i> {{ $details['label'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif

<hr>

<!-- Administration Section: Change Policy Assignment -->
<div class="row">
    <div class="col-md-12">
        <h4 style="font-weight:bold; margin-bottom: 15px;"><i class="fas fa-exchange-alt"></i> Assign New Policy</h4>
        <form action="{{ route('storeops.admin.rules.assign') }}" method="POST" id="assignPolicyForm">
            @csrf
            <input type="hidden" name="category_id" value="{{ $category->id }}">
            
            <div class="input-group">
                <!-- Javascript will copy options from the hidden master select here -->
                <select name="profile_id" class="form-control" id="localPolicySelect" required></select>
                <span class="input-group-btn">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Change policy assignment? This affects all future receiving workflows for {{ $category->name }}.')">
                        Apply Ruleset
                    </button>
                </span>
            </div>
        </form>
    </div>
</div>

<script>
    // Copy options from hidden master layout container into this specific form
    $('#localPolicySelect').html($('#masterPolicySelect').html());
</script>