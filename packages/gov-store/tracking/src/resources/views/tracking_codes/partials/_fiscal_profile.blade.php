<x-gs::box :title="__('govtracking::general.task.budget_question')">
    <div class="row">
        <div class="col-md-4 form-group">
            <label for="fiscal_year_input">{{ __('govtracking::general.task.fiscal_year') }}</label>
            <select name="fiscal_year" id="fiscal_year_input" class="form-control select2" required>
                <option value="">{{ __('govtracking::general.task.select_year') }}</option>
                @foreach(['2025-2026', '2026-2027', '2027-2028'] as $fy)
                    <option value="{{ $fy }}" {{ old('fiscal_year', $trackingCode?->fiscal_year) == $fy ? 'selected' : '' }}>{{ $fy }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4 form-group">
            <label for="budget_segment_display">{{ __('govtracking::general.task.budget_segment') }}</label>
            <input type="text" id="budget_segment_display" class="form-control" value="{{ __('govtracking::general.task.segment_budget', ['segment' => $initiative->primary_funding]) }}" disabled>
        </div>
        <div class="col-md-4 form-group">
            <label for="funding_type_input">{{ __('govtracking::general.task.subfund') }}</label>
            <select name="funding_type_id" id="funding_type_input" class="form-control" required>
                <option value="">{{ __('govtracking::general.task.select_source') }}</option>
                @foreach($fundingTypes as $type)
                    <option value="{{ $type->id }}" {{ old('funding_type_id', $trackingCode?->funding_type_id) == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
</x-gs::box>
