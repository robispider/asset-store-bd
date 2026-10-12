<div class="box box-solid">
    <div class="box-header with-border"><h3 class="box-title text-aqua">{{ __('govtracking::general.task.identity') }}</h3></div>
    <div class="box-body">
        <div class="row">
            <div class="col-md-4 form-group has-feedback" id="tracking-code-group">
                <label>{{ __('govtracking::general.task.tracking_code') }}</label>
                @if(isset($trackingCode))
                    <input type="text" class="form-control input-lg" value="{{ $trackingCode->tracking_code }}" disabled>
                    <input type="hidden" name="tracking_code" value="{{ $trackingCode->tracking_code }}">
                @else
                    <input type="text" name="tracking_code" id="tracking_code_input" class="form-control input-lg" value="{{ old('tracking_code') }}" placeholder="{{ __('govtracking::general.ui.extra_e_g_ict_2027_01') }}" required autocomplete="off">
                    <span class="fa form-control-feedback" id="tracking-code-icon" style="top: 25px; font-size: 20px; line-height: 45px;"></span>
                @endif
                <p class="help-block text-sm" id="tracking-code-help">{{ __('govtracking::general.task.code_help') }}</p>
            </div>

            <div class="col-md-8 form-group">
                <label>{{ __('govtracking::general.task.task_title') }}</label>
                <input type="text" name="task_title" class="form-control input-lg" value="{{ old('task_title', $trackingCode?->task_title) }}" placeholder="{{ __('govtracking::general.ui.extra_e_g_supply_of_equipment_for_sylhet_zone') }}" required>
            </div>
        </div>

        @if(!isset($trackingCode))
            <div class="form-group">
                <label>{{ __('govtracking::general.task.memo') }}</label>
                <input type="file" name="order_pdf" class="form-control" accept="application/pdf" {{ $initiative->require_documents ? 'required' : '' }}>
                @if($initiative->require_documents)
                    <p class="help-block text-red"><i class="fa fa-exclamation-circle"></i> {{ __('govtracking::general.task.memo_required') }}</p>
                @endif
            </div>
        @endif
    </div>
</div>
