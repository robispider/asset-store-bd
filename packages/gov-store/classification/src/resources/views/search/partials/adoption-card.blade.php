@php
    $user = auth()->user();
    $isSuperAdmin = $tenantContext->isGlobal;
    $isCompanyAdmin = $tenantContext->isCompanyAdmin;
    
    // Check if the current user is an Admin or SuperUser
    $canManageCollections = $user && ($user->isSuperUser() || $user->hasAccess('admin'));

    $isGlobal = ($governance && $governance->governance_type === 'global');
    $scopeNoun = ($activeScopeType === 'company') ? 'organization' : 'office location';
@endphp

<div id="adoption-card-container">
    @if($node->level < 4)
        <!-- ==============================================
             FOLDER ACTION WORKSPACE (Segments, Families, Classes)
             ============================================== -->
        <div class="box box-solid classify-inline-4412da54">
            <div class="box-body classify-inline-f8d354e0">
                <h4 class="classify-inline-7c94f291"><i class="fas fa-folder-open text-warning"></i> Folder Actions</h4>
                <p class="text-muted classify-inline-eefff2f8">This reference code is a <strong>Level {{ $node->level }} Folder</strong>. You can perform recursive operations on all descendant commodities under this branch.</p>
                
                <div class="classify-inline-2f92a4a1">
                    <button class="btn btn-warning btn-block" onclick="triggerBulkAdoption(['{{ $node->code }}'])" class="classify-inline-9c510dc4">
                        <i class="fas fa-rocket"></i> Adopt All Descendant Commodities
                    </button>
                    
                    @if($canManageCollections)
                        <button class="btn btn-purple btn-block" onclick="triggerAddToCollection(['{{ $node->code }}'])" class="classify-inline-cb6b1638">
                            <i class="fas fa-boxes"></i> Add All Commodities to Collection
                        </button>
                    @endif
                </div>
            </div>
        </div>

    @elseif(!$currentMapping)
        <!-- STATE 1: No Category Exists -->
        <div class="box box-solid classify-inline-86cf9c6b">
            <div class="box-body classify-inline-f8d354e0">
                <h4 class="classify-inline-7c94f291"><i class="fas fa-times-circle text-danger"></i> {{ __('classification::texts.adoption_no_category_exists') }}</h4>
                <p class="text-muted classify-inline-eefff2f8">{{ __('classification::texts.adoption_not_linked_desc') }}</p>
                
                <form id="provision-category-form" class="classify-inline-5fa693f3">
                    <input type="hidden" id="prov_unspsc_code" value="{{ $node->code }}">
                    
                    <div class="row classify-inline-4a3180e2">
                        <div class="col-sm-8">
                            <label>{{ __('classification::texts.adoption_label_category_name') }}</label>
                            <input type="text" id="prov_custom_name" class="form-control" value="{{ $node->title_en }}" required>
                        </div>
                        <div class="col-sm-4">
                            <label>{{ __('classification::texts.adoption_label_type') }}</label>
                            <select id="prov_category_type" class="form-control" required>
                                <option value="asset">{{ __('classification::texts.adoption_type_asset') }}</option>
                                <option value="consumable" selected>{{ __('classification::texts.adoption_type_consumable') }}</option>
                                <option value="accessory">{{ __('classification::texts.adoption_type_accessory') }}</option>
                                <option value="component">{{ __('classification::texts.adoption_type_component') }}</option>
                                <option value="license">{{ __('classification::texts.adoption_type_license') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Super Admin Governance Controls -->
                    @if($isSuperAdmin)
                        <div class="form-group classify-inline-3ed26cc4">
                            <label class="classify-inline-7e0142b5">{{ __('classification::texts.adoption_label_governance_availability') }}</label>
                            
                            <div class="radio">
                                <label class="classify-inline-78d7af8b">
                                    <input type="radio" name="governance_type" value="global" checked id="gov-global-radio">
                                    {{ __('classification::texts.adoption_gov_shared_standard') }}
                                </label>
                                <p class="text-muted classify-inline-21cbdd6e">{{ __('classification::texts.adoption_gov_available_globally') }}</p>
                            </div>
                            
                            <div class="radio classify-inline-b62ee557">
                                <label class="classify-inline-78d7af8b">
                                    <input type="radio" name="governance_type" value="company" id="gov-company-radio">
                                    {{ __('classification::texts.adoption_gov_org_private') }}
                                </label>
                                <p class="text-muted classify-inline-21cbdd6e">{{ __('classification::texts.adoption_gov_assign_org') }}</p>
                            </div>

                            <div id="company-assignment-div" class="classify-inline-c87077d8">
                                <select class="form-control input-sm select2 classify-inline-69d66e5b" id="prov_target_company">
                                    <option value="">{{ __('classification::texts.adoption_label_select_company') }}</option>
                                    @foreach(\App\Models\Company::orderBy('name')->get() as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @else
                        <!-- Regular User Transparent Context Notice -->
                        <div class="alert alert-info classify-inline-28e75c4e">
                            <i class="fas fa-info-circle"></i> {{ __('classification::texts.adoption_notice_secure_scope') }}
                        </div>
                    @endif

                    <div class="text-right classify-inline-5fa693f3">
                        <button type="submit" class="btn btn-primary" id="btn-provision">
                            <i class="fas fa-plus"></i> {{ __('classification::texts.adoption_btn_create_adopt') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @elseif($isGlobal)
        <!-- STATE 2: Globally Shared Standard -->
        <div class="box box-solid classify-inline-008c14e2">
            <div class="box-body classify-inline-f8d354e0">
                <h4 class="classify-inline-7c94f291"><i class="fas fa-globe text-aqua"></i> {{ __('classification::texts.adoption_gov_shared_standard') }}</h4>
                <p class="lead classify-inline-d298dd06">{{ $currentMapping->category?->name ?? 'Category' }}</p>
                <p class="text-muted classify-inline-eefff2f8">This official classification is globally available. It is already visible and ready for use in your local office dropdowns.</p>
                
                @if($canManageCollections)
                    <div class="classify-inline-f7c5c400">
                        <button type="button" class="btn btn-purple btn-block" onclick="triggerAddToCollection(['{{ $node->code }}'])">
                            <i class="fas fa-boxes"></i> Add to Collection
                        </button>
                    </div>
                @endif
            </div>
        </div>

    @elseif($isCompanyAdopted)
        <!-- STATE 3: Company Standard -->
        <div class="box box-solid classify-inline-adbdda45">
            <div class="box-body classify-inline-f8d354e0">
                <h4 class="classify-inline-7c94f291"><i class="fas fa-university text-purple"></i> Used by your Ministry / Organization</h4>
                <p class="lead classify-inline-d298dd06">{{ $currentMapping->category?->name ?? 'Category' }}</p>
                <p class="text-muted classify-inline-eefff2f8">Your parent Ministry has adopted this classification. It is already visible and ready for use in your local office.</p>
                
                <div class="classify-inline-2f92a4a1">
                    @if($isCompanyAdmin)
                        <button class="btn btn-default btn-abandon btn-block" data-id="{{ $currentMapping->category_id }}" class="classify-inline-2d98adf5">
                            <i class="fas fa-times"></i> Stop Using for Ministry
                        </button>
                    @endif
                    
                    @if($canManageCollections)
                        <button type="button" class="btn btn-purple btn-block" onclick="triggerAddToCollection(['{{ $node->code }}'])">
                            <i class="fas fa-boxes"></i> Add to Collection
                        </button>
                    @endif
                </div>
            </div>
        </div>

    @elseif($isLocationAdopted)
        <!-- STATE 4: Location Standard -->
        <div class="box box-solid classify-inline-ee49b19e">
            <div class="box-body classify-inline-f8d354e0">
                <h4 class="classify-inline-7c94f291"><i class="fas fa-map-marker-alt text-success"></i> Used by your Local Office</h4>
                <p class="lead classify-inline-d298dd06">{{ $currentMapping->category?->name ?? 'Category' }}</p>
                <p class="text-muted classify-inline-eefff2f8">This classification was adopted specifically for your local office building.</p>

                <div class="classify-inline-2f92a4a1">
                    <button class="btn btn-default btn-abandon btn-block" data-id="{{ $currentMapping->category_id }}" class="classify-inline-2d98adf5">
                        <i class="fas fa-times"></i> {{ __('classification::texts.adoption_btn_stop_using') }}
                    </button>
                    
                    @if($canManageCollections)
                        <button type="button" class="btn btn-purple btn-block" onclick="triggerAddToCollection(['{{ $node->code }}'])">
                            <i class="fas fa-boxes"></i> Add to Collection
                        </button>
                    @endif
                </div>
            </div>
        </div>

    @else
        <!-- STATE 5: Mapped, but NOT Adopted Anywhere Yet -->
        <div class="box box-solid classify-inline-4412da54">
            <div class="box-body classify-inline-f8d354e0">
                <h4 class="classify-inline-7c94f291"><i class="fas fa-link text-warning"></i> Available for Adoption</h4>
                <p class="lead classify-inline-d298dd06">{{ $currentMapping->category?->name ?? 'Private Category' }}</p>
                <p class="text-muted classify-inline-eefff2f8">This classification is not currently in use by your Ministry or Office.</p>

                <div class="classify-inline-2f92a4a1">
                    <button class="btn btn-success btn-adopt btn-block" data-id="{{ $currentMapping->category_id }}" class="classify-inline-2d98adf5">
                        <i class="fas fa-check"></i> {{ $isCompanyAdmin ? 'Adopt for Ministry' : 'Adopt for Local Office' }}
                    </button>
                    
                    @if($canManageCollections)
                        <button type="button" class="btn btn-purple btn-block" onclick="triggerAddToCollection(['{{ $node->code }}'])">
                            <i class="fas fa-boxes"></i> Add to Collection
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    function reloadWorkspace() {
        if (typeof loadWorkspaceDetails === 'function') {
            loadWorkspaceDetails('{{ $node->code }}');
        } else {
            window.location.reload();
        }
    }

    // Toggle Superadmin Company Assignment Dropdown
    jQuery(document).on('change', 'input[name="governance_type"]', function() {
        if (jQuery(this).val() === 'company') {
            jQuery('#company-assignment-div').slideDown(200);
            jQuery('#prov_target_company').prop('required', true);
        } else {
            jQuery('#company-assignment-div').slideUp(200);
            jQuery('#prov_target_company').prop('required', false).val('').trigger('change');
        }
    });

    // Provision Action
    jQuery('#provision-category-form').on('submit', function(e) {
        e.preventDefault();
        const btn = jQuery('#btn-provision');
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        const payload = {
            _token: '{{ csrf_token() }}',
            unspsc_code: jQuery('#prov_unspsc_code').val(),
            custom_name: jQuery('#prov_custom_name').val(),
            category_type: jQuery('#prov_category_type').val(),
        };

        if (jQuery('input[name="governance_type"]').length > 0) {
            payload.governance_type = jQuery('input[name="governance_type"]:checked').val();
            payload.target_company_id = jQuery('#prov_target_company').val();
        }

        jQuery.post('{{ route("gov.catalog.adoption.provision") }}', payload)
        .done(function() {
            reloadWorkspace();
        }).fail(function(xhr) {
            alert('{{ __('classification::texts.adoption_js_provisioning_failed') }}' + (xhr.responseJSON?.message || 'Error'));
            btn.html('<i class="fas fa-plus"></i> {{ __('classification::texts.adoption_btn_create_adopt') }}').prop('disabled', false);
        });
    });

    // Adopt Action
    jQuery('.btn-adopt').on('click', function() {
        const btn = jQuery(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        jQuery.post('{{ route("gov.catalog.adoption.adopt") }}', {
            _token: '{{ csrf_token() }}', category_id: btn.data('id')
        }).done(function() { reloadWorkspace(); });
    });

    // Abandon Action
    jQuery('.btn-abandon').on('click', function() {
        if(!confirm('{{ __('classification::texts.adoption_js_confirm_remove') }}')) return;
        const btn = jQuery(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        jQuery.post('{{ route("gov.catalog.adoption.abandon") }}', {
            _token: '{{ csrf_token() }}', category_id: btn.data('id')
        }).done(function() { reloadWorkspace(); }).fail(function(xhr) {
            alert('Governance Blocked: ' + (xhr.responseJSON?.message || 'Error'));
            btn.html('<i class="fas fa-times"></i> {{ __('classification::texts.adoption_btn_stop_using') }}').prop('disabled', false);
        });
    });
});
</script>