@php
    $activeSpec = old('specificity_level', $trackingCode?->specificity_level ?? '2_CATEGORY');
@endphp

<div class="box box-solid">
    <div class="box-header with-border"><h3 class="box-title text-blue">{{ __('govtracking::general.task.strategy_question') }}</h3></div>
    <div class="box-body">
        <label>{{ __('govtracking::general.task.select_strategy') }}</label>

        <div class="radio">
            <label>
                <input type="radio" name="specificity_level" value="1_BLANKET" {{ $activeSpec === '1_BLANKET' ? 'checked' : '' }}>
                <strong>{{ __('govtracking::general.ui.open_allocation') }}</strong> {{ __('govtracking::general.task.quantity_mode') }}
            </label>
        </div>

        <div class="radio">
            <label>
                <input type="radio" name="specificity_level" value="2_CATEGORY" {{ $activeSpec === '2_CATEGORY' ? 'checked' : '' }}>
                <strong>{{ __('govtracking::general.ui.category_targets') }}</strong> {{ __('govtracking::general.task.category_help') }}
            </label>
        </div>

        <div class="radio">
            <label>
                <input type="radio" name="specificity_level" value="3_MATRIX" {{ $activeSpec === '3_MATRIX' ? 'checked' : '' }}>
                <strong>{{ __('govtracking::general.task.office_mode') }}</strong> {{ __('govtracking::general.task.office_help') }}
            </label>
        </div>
    </div>
</div>
