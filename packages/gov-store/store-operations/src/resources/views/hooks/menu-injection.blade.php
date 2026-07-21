@php
    $user = auth()->user();
    $isStorekeeper = false;
    $isApprover = false;
    
    if ($user) {
        $isStorekeeper = $user->isSuperUser() || 
            \GovStore\OfficeMembership\Models\OfficeResponsibility::where('user_id', $user->id)
                ->where('role_slug', 'storekeeper')
                ->exists();
                
        $isApprover = $user->isSuperUser() || 
            \GovStore\OfficeMembership\Models\OfficeResponsibility::where('user_id', $user->id)
                ->whereIn('role_slug', ['primary_approver', 'final_approver'])
                ->exists();
    }
@endphp

<script>
document.addEventListener("DOMContentLoaded", function() {
    let sidebar = document.querySelector('.sidebar-menu');
    
    if (sidebar) {
        let menuHtml = `
            <li class="treeview" id="govstore-menu">
                <a href="#">
                    <i class="fa fa-shopping-cart text-aqua"></i> 
                    <span>Gov-Store Portal</span>
                    <span class="pull-right-container">
                        <i class="fa fa-angle-left pull-right"></i>
                    </span>
                </a>
                <ul class="treeview-menu">
                    <!-- EMPLOYEE WORKFLOWS -->
                    <li class="header" style="color: #72afd2; font-size: 9px; padding: 3px 15px; letter-spacing: 1px;">MY SELF SERVICE</li>
                    <li>
                        <a href="{{ route('gov.requests.catalog') }}">
                            <i class="fa fa-circle-o text-green"></i> Browse Catalog
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('gov.requests.user.index') }}">
                            <i class="fa fa-circle-o text-blue"></i> Track Requests
                        </a>
                    </li>
                    
                    @if($isApprover)
                        <!-- APPROVER WORKFLOWS -->
                        <li class="header" style="color: #72afd2; font-size: 9px; padding: 3px 15px; letter-spacing: 1px;">OFFICE APPROVALS</li>
                        <li>
                            <a href="{{ route('gov.requests.admin.index') }}">
                                <i class="fa fa-circle-o text-yellow"></i> Approval Queue
                            </a>
                        </li>
                    @endif

                    @if($isStorekeeper)
                        <!-- STORES MANAGEMENT WORKFLOWS -->
                        <li class="header" style="color: #72afd2; font-size: 9px; padding: 3px 15px; letter-spacing: 1px;">STORES & ACCOUNTING</li>
                        <li>
                            <a href="{{ route('storeops.register.index') }}">
                                <i class="fa fa-circle-o text-aqua"></i> Stock Register Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('gov.requests.fulfillment.index') }}">
                                <i class="fa fa-circle-o"></i> Fulfillment Queue
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('storeops.receipts.create') }}">
                                <i class="fa fa-circle-o"></i> Receive Goods (GRN)
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('storeops.issues.create') }}">
                                <i class="fa fa-circle-o"></i> Ad-Hoc Direct Issue
                            </a>
                        </li>
                    @endif
                </ul>
            </li>
        `;
        
        // Inject cleanly at the bottom of the existing list
        sidebar.insertAdjacentHTML('beforeend', menuHtml);
        
        // Re-initialize AdminLTE treeview listeners for the dynamically injected element
        if (typeof $.fn.tree === 'function') {
            $('#govstore-menu').tree();
        }
    }
});
</script>
