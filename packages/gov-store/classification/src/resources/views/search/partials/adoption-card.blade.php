@php
    $user = auth()->user();
    $isSuperAdmin = $user->isSuperUser() || $user->hasAccess('admin');
@endphp

<div id="adoption-card-container">
    @if(!$currentMapping)
        <!-- STATE 1: No Category Exists -->
        <div class="box box-solid" style="margin-top: 20px; border: 1px solid #d2d6de; border-top: 3px solid #dd4b39;">
            <div class="box-body" style="padding: 20px;">
                <h4 style="margin-top: 0; font-weight: bold;"><i class="fas fa-times-circle text-danger"></i> No Category Exists</h4>
                <p class="text-muted" style="font-size: 13px;">This official classification is not linked to an operational category.</p>
                
                <form id="provision-category-form" style="margin-top: 20px;">
                    <input type="hidden" id="prov_unspsc_code" value="{{ $node->code }}">
                    
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-sm-8">
                            <label>Category Name</label>
                            <input type="text" id="prov_custom_name" class="form-control" value="{{ $node->title_en }}" required>
                        </div>
                        <div class="col-sm-4">
                            <label>Type</label>
                            <select id="prov_category_type" class="form-control" required>
                                <option value="asset">Asset</option>
                                <option value="consumable" selected>Consumable</option>
                                <option value="accessory">Accessory</option>
                                <option value="component">Component</option>
                                <option value="license">License</option>
                            </select>
                        </div>
                    </div>

                    <!-- Super Admin Governance Controls -->
                    @if($isSuperAdmin)
                        <div class="form-group" style="background: #f9fafb; padding: 15px; border-radius: 4px; border: 1px solid #eee;">
                            <label style="display: block; margin-bottom: 10px; color: #333;">Governance & Availability</label>
                            
                            <div class="radio">
                                <label style="font-weight: bold;">
                                    <input type="radio" name="governance_type" value="global" checked id="gov-global-radio">
                                    Shared Government Standard
                                </label>
                                <p class="text-muted" style="font-size: 12px; margin-left: 20px;">Available globally to all organizations.</p>
                            </div>
                            
                            <div class="radio" style="margin-top: 15px;">
                                <label style="font-weight: bold;">
                                    <input type="radio" name="governance_type" value="company" id="gov-company-radio">
                                    Organization Standard (Private)
                                </label>
                                <p class="text-muted" style="font-size: 12px; margin-left: 20px;">Assign exclusively to a specific organization.</p>
                            </div>

                            <div id="company-assignment-div" style="display: none; margin-top: 10px; margin-left: 20px;">
                                <select id="prov_target_company" class="form-control input-sm select2" style="width: 100%;">
                                    <option value="">-- Select Company --</option>
                                    @foreach(\App\Models\Company::orderBy('name')->get() as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @else
                        <!-- Regular User Transparent Context Notice -->
                        <div class="alert alert-info" style="font-size: 12px; padding: 10px; background-color: #f4f8fa !important; border-color: #bce8f1 !important; color: #31708f !important;">
                            <i class="fas fa-info-circle"></i> This category will be created securely within your organization's operational scope.
                        </div>
                    @endif

                    <div class="text-right" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" id="btn-provision">
                            <i class="fas fa-plus"></i> Create & Adopt
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @elseif(!$isAdoptedByMe)
        <!-- STATE 2: Mapped, but NOT Adopted -->
        <div class="box box-solid" style="margin-top: 20px; border: 1px solid #d2d6de; border-top: 3px solid #f39c12;">
            <div class="box-body" style="padding: 20px;">
                <h4 style="margin-top: 0; font-weight: bold;"><i class="fas fa-link text-warning"></i> Mapped Category</h4>
                <p class="lead" style="margin-bottom: 5px; color: #333;">{{ $currentMapping->category->name }}</p>
                
                <table class="table table-condensed text-muted" style="margin-top: 15px; font-size: 13px;">
                    <tr>
                        <th style="width: 150px; border-top: none;">Governance:</th>
                        <td style="border-top: none;">
                           @if($governance && $governance->governance_type === 'global')
                                <span class="text-green"><i class="fas fa-globe"></i> Shared Government Standard</span>
                            @else
                                <span class="text-orange"><i class="fas fa-building"></i> Organization Standard</span><br>
                                <small>Owned by: {{ $governance->originatingCompany?->name ?? 'Unknown' }}</small>
                            @endif
                        </td>
                    </tr>
                </table>

                <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <!-- Any authorized user can adopt it if they can see it -->
                    <button class="btn btn-success btn-adopt" data-id="{{ $currentMapping->category_id }}">
                        <i class="fas fa-check"></i> Use Category
                    </button>
                </div>
            </div>
        </div>

    @else
        <!-- STATE 3: Mapped AND Adopted -->
        <div class="box box-solid" style="margin-top: 20px; border: 1px solid #d2d6de; border-top: 3px solid #00a65a;">
            <div class="box-body" style="padding: 20px;">
                <h4 style="margin-top: 0; font-weight: bold;"><i class="fas fa-link text-success"></i> Mapped Category</h4>
                <p class="lead" style="margin-bottom: 5px; color: #333;">{{ $currentMapping->category->name }}</p>

                <table class="table table-condensed text-muted" style="margin-top: 15px; font-size: 13px;">
                    <tr>
                        <th style="width: 150px; border-top: none;">Governance:</th>
                        <td style="border-top: none;">
                           @if($governance && $governance->governance_type === 'global')
                                <span class="text-green"><i class="fas fa-globe"></i> Shared Government Standard</span>
                            @else
                                <span class="text-orange"><i class="fas fa-building"></i> Organization Standard</span><br>
                                <small>Owned by: {{ $governance->originatingCompany?->name ?? 'Unknown' }}</small>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td class="text-success"><strong>✓ Used by your organization</strong></td>
                    </tr>
                </table>

                <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button class="btn btn-default btn-abandon" data-id="{{ $currentMapping->category_id }}">
                        <i class="fas fa-times"></i> Stop Using
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
$(document).ready(function() {
    function reloadWorkspace() {
        if (typeof loadWorkspaceDetails === 'function') {
            loadWorkspaceDetails('{{ $node->code }}');
        } else {
            window.location.reload();
        }
    }

    // Toggle Superadmin Company Assignment Dropdown
    $('input[name="governance_type"]').on('change', function() {
        if ($(this).val() === 'company') {
            $('#company-assignment-div').slideDown(200);
            $('#prov_target_company').prop('required', true);
        } else {
            $('#company-assignment-div').slideUp(200);
            $('#prov_target_company').prop('required', false).val('').trigger('change');
        }
    });

    // Provision Action
    $('#provision-category-form').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btn-provision');
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        
        const payload = {
            _token: '{{ csrf_token() }}',
            unspsc_code: $('#prov_unspsc_code').val(),
            custom_name: $('#prov_custom_name').val(),
            category_type: $('#prov_category_type').val(),
        };

        // Inject superadmin fields only if they exist in the DOM
        if ($('input[name="governance_type"]').length > 0) {
            payload.governance_type = $('input[name="governance_type"]:checked').val();
            payload.target_company_id = $('#prov_target_company').val();
        }

        $.post('{{ route("gov.catalog.adoption.provision") }}', payload)
        .done(function() {
            reloadWorkspace();
        }).fail(function(xhr) {
            alert('Provisioning failed: ' + (xhr.responseJSON?.message || 'Error'));
            btn.html('<i class="fas fa-plus"></i> Create & Adopt').prop('disabled', false);
        });
    });

    // Adopt Action
    $('.btn-adopt').on('click', function() {
        const btn = $(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        $.post('{{ route("gov.catalog.adoption.adopt") }}', {
            _token: '{{ csrf_token() }}', category_id: btn.data('id')
        }).done(function() { reloadWorkspace(); });
    });

    // Abandon Action
    $('.btn-abandon').on('click', function() {
        if(!confirm('Are you sure you want to remove this category?')) return;
        const btn = $(this);
        btn.html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
        $.post('{{ route("gov.catalog.adoption.abandon") }}', {
            _token: '{{ csrf_token() }}', category_id: btn.data('id')
        }).done(function() { reloadWorkspace(); }).fail(function(xhr) {
            alert('Governance Blocked: ' + (xhr.responseJSON?.message || 'Error'));
            btn.html('<i class="fas fa-times"></i> Stop Using').prop('disabled', false);
        });
    });
});
</script>