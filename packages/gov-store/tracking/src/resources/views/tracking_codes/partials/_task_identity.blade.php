<x-gs::box :title="__('govtracking::general.task.identity')">
    <div class="row">
        <div class="col-md-4 form-group has-feedback" id="tracking-code-group">
            <label for="tracking_code_input">{{ __('govtracking::general.task.tracking_code') }}</label>
            @if(isset($trackingCode))
                <input type="text" id="tracking_code_input" class="form-control input-lg gs-tracking-code" value="{{ $trackingCode->tracking_code }}" disabled>
                <input type="hidden" name="tracking_code" value="{{ $trackingCode->tracking_code }}">
            @else
                <input type="text" name="tracking_code" id="tracking_code_input" class="form-control input-lg" value="{{ old('tracking_code') }}" placeholder="{{ __('govtracking::general.ui.extra_e_g_ict_2027_01') }}" required autocomplete="off">
                <span class="fa form-control-feedback gs-tracking-code-feedback" id="tracking-code-icon" aria-hidden="true"></span>
            @endif
            <p class="help-block gs-tracking-help" id="tracking-code-help">{{ __('govtracking::general.task.code_help') }}</p>
        </div>

        <div class="col-md-8 form-group">
            <label for="task_title_input">{{ __('govtracking::general.task.task_title') }}</label>
            <input type="text" name="task_title" id="task_title_input" class="form-control input-lg" value="{{ old('task_title', $trackingCode?->task_title) }}" placeholder="{{ __('govtracking::general.ui.extra_e_g_supply_of_equipment_for_sylhet_zone') }}" required>
        </div>
    </div>

    @if(!isset($trackingCode))
        <div class="form-group">
            <label for="order_pdf_input">{{ __('govtracking::general.task.memo') }}</label>
            <input type="file" name="order_pdf" id="order_pdf_input" class="form-control" accept="application/pdf" {{ $initiative->require_documents ? 'required' : '' }}>
            @if($initiative->require_documents)
                <p class="help-block gs-tracking-text-danger"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> {{ __('govtracking::general.task.memo_required') }}</p>
            @endif
        </div>
    @endif
</x-gs::box>
