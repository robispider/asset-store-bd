@extends('layouts/default')
@section('title', 'Copy Office Catalog')

@section('content')
<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-copy text-blue"></i> Clone Catalog</h3>
                <p class="text-muted" style="margin-bottom: 0; margin-top: 5px;">Rapidly provision your office by copying the exact catalog structure of another office within your Ministry.</p>
            </div>
            
            <div class="box-body" style="padding: 30px;">
                <!-- Step 1 -->
                <h4 style="font-weight: bold; margin-bottom: 20px;">1. Select Source Office</h4>
                <div class="form-group">
                    <label class="text-muted">Copy From:</label>
                    <select id="source-office" class="form-control input-lg select2" style="width: 100%;">
                        <option value="">-- Select an Office --</option>
                        @foreach($sourceOffices as $office)
                            <option value="{{ $office->id }}">{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="text-align: center; margin: 30px 0;">
                    <i class="fas fa-arrow-down fa-2x text-muted" style="opacity: 0.5;"></i>
                </div>

                <!-- Destination (Read Only) -->
                <div class="form-group">
                    <label class="text-muted">Destination (Your Active Office):</label>
                    <div class="well well-sm" style="background-color: #f4f8fa; border-color: #bce8f1; font-size: 16px; font-weight: bold; color: #31708f;">
                        <i class="fas fa-map-marker-alt"></i> {{ \App\Models\Location::find(app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId)->name ?? 'Unknown Office' }}
                    </div>
                </div>

                <!-- Step 2 -->
                <h4 style="font-weight: bold; margin-top: 30px; margin-bottom: 15px;">2. Configuration Rules</h4>
                <div class="checkbox">
                    <label style="font-size: 15px; color: #555;">
                        <input type="checkbox" checked disabled> Skip categories you already have (Automatic)
                    </label>
                </div>
                <div class="checkbox">
                    <label style="font-size: 15px; color: #555;">
                        <input type="checkbox" checked disabled> Keep your existing custom categories (Automatic)
                    </label>
                </div>
                <div class="checkbox">
                    <label style="font-size: 15px; color: #555;">
                        <input type="checkbox" disabled> Include their archived categories (Excluded by default)
                    </label>
                </div>
            </div>

            <div class="box-footer text-right" style="background-color: #f9f9f9; padding: 20px;">
                <button type="button" class="btn btn-default btn-lg" onclick="window.history.back();" style="margin-right: 10px;">Cancel</button>
                <button type="button" class="btn btn-primary btn-lg" id="btn-preview-copy" disabled>
                    <i class="fas fa-eye"></i> Preview Copy
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Include the Phase 2 Bulk Adoption Modal -->
@include('gov-classification::adopt.partials.bulk-preview')

@endsection

@section('moar_scripts')
@parent
<script>
$(document).ready(function() {
    $('#source-office').select2();

    $('#source-office').on('change', function() {
        if ($(this).val()) {
            $('#btn-preview-copy').prop('disabled', false);
        } else {
            $('#btn-preview-copy').prop('disabled', true);
        }
    });

    $('#btn-preview-copy').on('click', function() {
        const sourceId = $('#source-office').val();
        const btn = $(this);
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Fetching...');

        // Fetch codes from source office, then trigger Bulk Modal
        $.post('{{ route("gov.catalog.adopt.copy.fetch") }}', {
            _token: '{{ csrf_token() }}',
            source_location_id: sourceId
        }).done(function(res) {
            btn.prop('disabled', false).html('<i class="fas fa-eye"></i> Preview Copy');
            if (res.codes.length === 0) {
                alert("The selected office has no active categories to copy.");
                return;
            }
            triggerBulkAdoption(res.codes); // Opens the modal from Phase 2
        }).fail(function() {
            alert("Failed to fetch source catalog.");
            btn.prop('disabled', false).html('<i class="fas fa-eye"></i> Preview Copy');
        });
    });
});
</script>
@endsection