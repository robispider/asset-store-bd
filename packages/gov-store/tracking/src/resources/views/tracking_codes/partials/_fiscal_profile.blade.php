<div class="box box-solid">
    <div class="box-header with-border"><h3 class="box-title text-orange">3. Which budget funds this task?</h3></div>
    <div class="box-body">
        <div class="row">
            <div class="col-md-4 form-group">
                <label>Fiscal Year</label>
                <select name="fiscal_year" class="form-control select2" required>
                    <option value="">-- Select FY --</option>
                    @foreach(['2025-2026', '2026-2027', '2027-2028'] as $fy)
                        <option value="{{ $fy }}" {{ old('fiscal_year', $trackingCode?->fiscal_year) == $fy ? 'selected' : '' }}>{{ $fy }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 form-group">
                <label>Budget Main Segment</label>
                <input type="text" class="form-control" value="{{ $initiative->primary_funding }} Budget" disabled>
            </div>
            <div class="col-md-4 form-group">
                <label>Dynamic Sub Fund Source</label>
                <select name="funding_type_id" class="form-control" required>
                    <option value="">-- Select Sub-Source --</option>
                    @foreach($fundingTypes as $type)
                        <option value="{{ $type->id }}" {{ old('funding_type_id', $trackingCode?->funding_type_id) == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>