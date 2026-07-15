@auth
<script nonce="{{ csrf_token() }}">
$(document).ready(function() {
    
    console.log("Gov-Store: Starting navbar context switcher injection...");

    // =========================================================================
    // 1. TOP-BAR MULTI-OFFICE CONTEXT SWITCHER (NATIVE EMBED)
    // =========================================================================
    @php
        $user = auth()->user();
        $isAdmin = $user->isSuperUser() || $user->hasAccess('admin');

        $activeMemberships = \GovStore\OfficeMembership\Models\OfficeMembership::with('location')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $activeLocations = $isAdmin 
            ? \App\Models\Location::whereHas('profile', function($q) { $q->where('lifecycle_status', 'operational'); })->get() 
            : $activeMemberships->pluck('location')->filter();

        $currentMembershipId = session('gov_working_membership_id');
        
        $currentWorkingName = null;
        if ($currentMembershipId) {
            $activeMem = $activeMemberships->where('id', $currentMembershipId)->first();
            $currentWorkingName = $activeMem ? $activeMem->location->name : null;
        } elseif ($isAdmin) {
            $currentWorkingName = null;
        } else {
            $currentWorkingName = \App\Models\Location::find($user->location_id)?->name;
        }
    @endphp

    @if($activeLocations->count() > 0)
        var navList = $('.navbar-custom-menu ul.nav').length ? $('.navbar-custom-menu ul.nav') : $('.navbar-custom-menu ul');
        
        if (navList.length && !$('#gov-context-switcher-item').length) {
            
            var activeLabel = '{{ $currentWorkingName }}' ? '{{ $currentWorkingName }}' : 'Global Overview';
            var activeClassLabel = '{{ $currentWorkingName }}' ? 'text-yellow' : 'text-green';

            var dropdownHtml = '<li class="dropdown" id="gov-context-switcher-item" style="border-right: 1px solid rgba(255,255,255,0.1);">' +
                '<a href="#" class="dropdown-toggle" data-toggle="dropdown" style="color: white; font-weight: bold; padding: 15px 15px;">' +
                    '<i class="fas fa-hotel"></i> &nbsp;Working As: <span class="' + activeClassLabel + '">' + activeLabel + '</span> &nbsp;<span class="caret"></span>' +
                '</a>' +
                '<ul class="dropdown-menu" style="background-color: #fff; width: 280px; padding: 5px 0;">' +
                    '<li class="header" style="padding: 8px 15px; font-size: 11px; color: #777; border-bottom: 1px solid #f4f4f4; background-color: #fafafa; font-weight: bold;">CHOOSE WORKING CONTEXT</li>';

            @if($isAdmin)
                var isGlobalActive = !'{{ $currentWorkingName }}';
                dropdownHtml += '<li style="border-bottom: 1px dashed #eee;">' +
                    '<a href="#" onclick="event.preventDefault(); document.getElementById(\'switch-context-form-global\').submit();" style="padding: 10px 15px; display: block; clear: both; font-weight: bold; color: #3c8dbc; background: ' + (isGlobalActive ? '#eef7ff' : 'transparent') + '">' +
                        '<i class="fas fa-globe" style="margin-right: 10px;"></i>' +
                        '🌎 Global Overview (All Offices)' +
                    '</a>' +
                    '<form id="switch-context-form-global" action="{{ route("gov.membership.switch") }}" method="POST" style="display:none;">' +
                        '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                        '<input type="hidden" name="location_id" value="0">' +
                    '</form>' +
                '</li>';
            @endif

            @foreach($activeLocations as $loc)
                @php 
                    $isCurrent = $currentMembershipId && $activeMemberships->where('id', $currentMembershipId)->first()?->location_id === $loc->id; 
                    if (!$currentMembershipId && $isAdmin && session('gov_working_location_id') == $loc->id) {
                        $isCurrent = true;
                    }
                @endphp
                
                dropdownHtml += '<li style="border-bottom: 1px solid #f9f9f9;">' +
                    '<a href="#" onclick="event.preventDefault(); document.getElementById(\'switch-context-form-{{ $loc->id }}\').submit();" style="padding: 10px 15px; display: block; clear: both; font-weight: normal; line-height: 1.42857143; color: #333; white-space: nowrap; background: {{ $isCurrent ? "#f4f4f4" : "transparent" }}">' +
                        '<i class="fas fa-map-marker-alt {{ $isCurrent ? "text-green" : "text-muted" }}" style="margin-right: 10px;"></i>' +
                        '<strong>' + '{{ addslashes($loc->name) }}' + '</strong>' +
                    '</a>' +
                    '<form id="switch-context-form-{{ $loc->id }}" action="{{ route("gov.membership.switch") }}" method="POST" style="display:none;">' +
                        '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                        @if($isAdmin)
                            '<input type="hidden" name="location_id" value="{{ $loc->id }}">' +
                        @else
                            @php $memId = $activeMemberships->where('location_id', $loc->id)->first()->id; @endphp
                            '<input type="hidden" name="membership_id" value="{{ $memId }}">' +
                        @endif
                    '</form>' +
                '</li>';
            @endforeach

            dropdownHtml += '</ul></li>';
            navList.first().prepend(dropdownHtml);
            console.log("Gov-Store: Navbar context switcher successfully loaded.");
        }
    @endif
});
</script>
@endauth