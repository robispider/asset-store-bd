<div class="well well-sm" style="background:#fff; margin-bottom: 15px;">
    <h5 style="margin-top:0;"><strong>{{ __('storeops::storeops.validation_checklist') }}</strong></h5>
    
    <!-- Real-time Progress Bar -->
    <div class="progress progress-xxs" style="margin-bottom: 10px; background-color: #eee;">
        <div class="progress-bar progress-bar-success" id="validationProgress" style="width: 0%"></div>
    </div>

    <!-- Server-driven Checklist items -->
    <ul class="list-unstyled" id="checklistRequirements" style="margin-bottom: 0; font-size:12px; line-height: 1.8;">
        <li class="text-muted"><i class="fa fa-info-circle"></i> {{ __('storeops::storeops.save_requirements') }}</li>
    </ul>
</div>