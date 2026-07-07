

<?php $__env->startSection('title', 'Provision Government Office'); ?>

<?php $__env->startSection('content'); ?>


<style>
    .onboarding-box {
        border-radius: 6px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #d2d6de;
        background: #fff;
    }
    .form-section-header {
        font-size: 15px;
        font-weight: bold;
        color: var(--main-theme-color, #3c8dbc);
        border-bottom: 2px solid #f4f4f4;
        padding-bottom: 8px;
        margin-top: 30px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-section-header:first-of-type {
        margin-top: 10px;
    }
    .form-section-header i {
        font-size: 17px;
    }
    .advisory-box {
        background: #fafafa;
        border-left: 4px solid var(--main-theme-color, #3c8dbc);
        padding: 15px;
        border-radius: 0 4px 4px 0;
        margin-bottom: 20px;
        border-top: 1px solid #eee;
        border-right: 1px solid #eee;
        border-bottom: 1px solid #eee;
    }
    .duplicate-alert-callout {
        background: #fffcf5;
        border-left: 4px solid #f39c12;
        padding: 15px;
        border-radius: 0 4px 4px 0;
        margin-bottom: 25px;
        border-top: 1px solid #faebcc;
        border-right: 1px solid #faebcc;
        border-bottom: 1px solid #faebcc;
    }
    .list-group-custom .list-group-item {
        background: transparent;
        border-left: none;
        border-right: none;
        padding: 10px 0;
        border-bottom: 1px dashed #ddd;
    }
    .list-group-custom .list-group-item:last-child {
        border-bottom: none;
    }
</style>

<div class="row">
    <!-- LEFT COLUMN: Step-Guided Provisioning Form -->
    <div class="col-md-7">
        <div class="box onboarding-box" style="border-top: 3px solid var(--main-theme-color, #3c8dbc);">
            <div class="box-header with-border" style="padding: 15px 20px;">
                <h3 class="box-title" style="font-weight: bold; font-size: 16px;">
                    <i class="fas fa-plus-circle"></i> Office Registration Workspace
                </h3>
            </div>
            
            <form action="<?php echo e(route('gov.org.provisioning.store')); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <div class="box-body" style="padding: 20px 25px;">
                    
                    <!-- SECTION 1: IDENTITY -->
                    <div class="form-section-header">
                        <i class="fas fa-id-card"></i> <span>1. Office Building Identity</span>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="name">Office Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control input-lg" placeholder="e.g. Debidwar Upazila Health Complex" required value="<?php echo e(old('name')); ?>">
                    </div>


                    <!-- SECTION 2: GEOGRAPHY -->
                    <div class="form-section-header" style="margin-top: 25px;">
                        <i class="fas fa-map-marked-alt"></i> <span>2. Geographical Boundary Tag</span>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="geoAreaSelector">Administrative Territory Boundary <span class="text-danger">*</span></label>
                        <select name="geo_area_id" id="geoAreaSelector" class="form-control" required style="width: 100%;">
                            <option value="">-- Type to search Division, Zila, Upazila or Union --</option>
                        </select>
                        <p class="help-block" style="margin-top: 6px;"><i class="fas fa-info-circle"></i> Mandatory. This locks the building to its standard geo-code parameters.</p>
                    </div>


                    <!-- SECTION 3: ADMINISTRATION & MAPPING -->
                    <div class="form-section-header" style="margin-top: 35px;">
                        <i class="fas fa-sitemap"></i> <span>3. Organizational Hierarchy & Setup</span>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="company_id">Ministry / Department Ownership (Optional)</label>
                                <select name="company_id" id="company_id" class="form-control select2" style="width: 100%;">
                                    <option value="">-- Standalone Office (No Ministry) --</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <option value="<?php echo e($company->id); ?>"><?php echo e($company->name); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label for="parent_id">Parent Regional/District Office (Optional)</label>
                                <select name="parent_id" id="parent_id" class="form-control select2" style="width: 100%;">
                                    <option value="">-- No Parent (Root Location) --</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $offices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parentLoc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <option value="<?php echo e($parentLoc->id); ?>"><?php echo e($parentLoc->name); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px; margin-bottom: 10px;">
                        <label for="office_admin_id">Delegate Office Administrator (Optional)</label>
                        <select name="office_admin_id" id="office_admin_id" class="form-control select2" style="width: 100%;">
                            <option value="">-- Leave Unassigned for Now --</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($user->id); ?>"><?php echo e($user->present()->fullName); ?> (<?php echo e($user->username); ?>)</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <p class="help-block" style="margin-top: 6px;">The delegated administrator receives email setup credentials to configure their own workflow roles.</p>
                    </div>

                </div>
                
                <div class="box-footer" style="padding: 15px 25px; background-color: #fafafa; border-top: 1px solid #f4f4f4;">
                    <a href="<?php echo e(route('gov.org.provisioning.index')); ?>" class="btn btn-default pull-left" style="padding: 8px 15px;">
                        <i class="fas fa-arrow-left"></i> Return to Registry
                    </a>
                    <button type="submit" class="btn btn-primary pull-right" style="padding: 8px 25px; font-weight: bold;">
                        <i class="fas fa-building"></i> Save & Provision Office
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RIGHT COLUMN: Guidelines & Smart Warnings -->
    <div class="col-md-5">
        
        <!-- Live Duplicate Checker Callout Widget -->
        <div class="duplicate-alert-callout" id="duplicateWidget" style="display: none;">
            <h4 style="font-weight: bold; margin-top: 0; color: #c0392b !important;">
                <i class="fas fa-exclamation-triangle text-warning"></i> Registry Warning: Similar Office Found
            </h4>
            <p class="text-muted" style="font-size: 13px; line-height: 1.5; margin-bottom: 15px;">
                An office belonging to the selected Ministry is already registered within this geographic boundary. Verify if this is an intended separate building before saving:
            </p>
            <ul id="duplicateList" class="list-group list-group-custom" style="margin-bottom: 10px;"></ul>
            <p class="text-muted" style="font-size: 11px; margin-bottom: 0; font-style: italic;">
                Note: This does not block registration; it acts as a data-integrity pre-check.
            </p>
        </div>

        <!-- Onboarding Advisory Panel -->
        <div class="box onboarding-box" style="border-top: 3px solid #d2d6de;">
            <div class="box-header with-border" style="padding: 15px 20px;">
                <h3 class="box-title" style="font-weight: bold; font-size: 15px;"><i class="fas fa-info-circle text-muted"></i> Field Deployment Guidelines</h3>
            </div>
            <div class="box-body" style="padding: 20px 25px;">
                <div class="advisory-box">
                    <p style="margin-bottom: 0; font-size: 13px; font-weight: bold; color: #333;">Spatial-First Principle</p>
                    <p class="text-muted" style="font-size: 12.5px; line-height: 1.6; margin-top: 5px; margin-bottom: 0;">
                        Every office physically exists somewhere. By establishing its standardized geographic territory first, you enable spatial audit tracking, proximity dispatching, and coverage density statistics.
                    </p>
                </div>

                <ul style="padding-left: 20px; line-height: 1.8; color: #555; font-size: 13px;">
                    <li><strong>Step 1 (Office Identity):</strong> Use standardized spellings aligned with government directories.</li>
                    <li><strong>Step 2 (Territory Tag):</strong> Select any administrative tier (Zila, Upazila, or Union) mapped inside the platform databases.</li>
                    <li><strong>Step 3 (Ownership):</strong> Assigning parent and ministry structures is fully optional on day-one and can be mapped during secondary configurations inside the Hub.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('moar_scripts'); ?>
<script>
$(document).ready(fontSelectionScript);

function fontSelectionScript() {
    // 1. Initialize Ajax Select2 searching over unrestricted geographic reference database
    $('#geoAreaSelector').select2({
        minimumInputLength: 2,
        ajax: {
            url: '<?php echo e(route("gov.geo.search")); ?>', // Query the shared library API
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    restrict_hid: '<?php echo e($restrictToHid); ?>' // Scopes search strictly within the ICT Officer's jurisdiction bounds
                };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        placeholder: "Search Division, District, Upazila, or Union..."
    });

    // 2. Live Duplicate Awareness Checking
    function checkDuplicates() {
        var companyId = $('#company_id').val();
        var geoAreaId = $('#geoAreaSelector').val();

        if (companyId && geoAreaId) {
            $.ajax({
                url: '<?php echo e(route("gov.org.provisioning.check-duplicate")); ?>',
                data: { company_id: companyId, geo_area_id: geoAreaId },
                dataType: 'json',
                success: function(data) {
                    if (data.length > 0) {
                        var listHtml = '';
                        $.each(data, function(index, item) {
                            listHtml += '<li class="list-group-item"><strong>' + item.name + '</strong></li>';
                        });
                        $('#duplicateList').html(listHtml);
                        $('#duplicateWidget').slideDown('fast');
                    } else {
                        $('#duplicateWidget').slideUp('fast');
                    }
                }
            });
        } else {
            $('#duplicateWidget').slideUp('fast');
        }
    }

    $('#company_id').on('change', checkDuplicates);
    $('#geoAreaSelector').on('change', checkDuplicates);
}
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts/default', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\git repo\asset-store-bd\packages\gov-store\organization\src\Providers/../resources/views/provisioning/create.blade.php ENDPATH**/ ?>