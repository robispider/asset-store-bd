@extends('layouts/default')

@section('title', 'Boundary Mappings Explorer')

@section('content')
<div class="row" style="margin-bottom: 15px;">
    <div class="col-md-12 text-right">
        <!-- Assign Trigger Button -->
        <button class="btn btn-primary btn-md" data-toggle="modal" data-target="#mappingModal">
            <i class="fas fa-plus"></i> Assign New Mapping Rule
        </button>
    </div>
</div>

<div class="row">
    <!-- Filter Sidebar Panel -->
    <div class="col-md-3">
        <div class="box box-solid box-default" style="border: 1px solid #d2d6de;">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-filter"></i> Filter Grid</h3>
            </div>
            <form action="{{ route('gov.scope.mappings') }}" method="GET">
                <div class="box-body">
                    <div class="form-group">
                        <label>Reference Type</label>
                        <select name="reference_type" class="form-control input-sm">
                            <option value="">-- All Types --</option>
                            <option value="category" {{ request('reference_type') === 'category' ? 'selected' : '' }}>Category</option>
                            <option value="model" {{ request('reference_type') === 'model' ? 'selected' : '' }}>Asset Model</option>
                            <option value="manufacturer" {{ request('reference_type') === 'manufacturer' ? 'selected' : '' }}>Manufacturer</option>
                            <option value="supplier" {{ request('reference_type') === 'supplier' ? 'selected' : '' }}>Supplier</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Scope Type</label>
                        <select name="scope_type" class="form-control input-sm">
                            <option value="">-- All Scopes --</option>
                            <option value="company" {{ request('scope_type') === 'company' ? 'selected' : '' }}>Company (Ministry)</option>
                            <option value="location" {{ request('scope_type') === 'location' ? 'selected' : '' }}>Location (Office)</option>
                        </select>
                    </div>
                </div>
                <div class="box-footer">
                    <a href="{{ route('gov.scope.mappings') }}" class="btn btn-sm btn-default pull-left">Reset</a>
                    <button type="submit" class="btn btn-sm btn-primary pull-right">Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Paginated Grid Table (Handles 10,000+ entries) -->
    <div class="col-md-9">
        <div class="box box-primary">
            <div class="box-body table-responsive" style="padding: 0;">
                <table class="table table-striped table-hover" style="margin-bottom: 0;">
                    <thead>
                        <tr style="background-color: #fcfcfc;">
                            <th style="padding-left: 15px;">Reference Item</th>
                            <th>Scoped Scope Boundary</th>
                            <th class="text-center" style="width: 100px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mappings as $map)
                            @php
                                $refName = 'Unknown Item';
                                if ($map->reference_type === 'category') $refName = \App\Models\Category::find($map->reference_id)?->name ?? 'Deleted Category';
                                if ($map->reference_type === 'model') $refName = \App\Models\AssetModel::find($map->reference_id)?->name ?? 'Deleted Model';
                                if ($map->reference_type === 'manufacturer') $refName = \App\Models\Manufacturer::find($map->reference_id)?->name ?? 'Deleted Manufacturer';
                                if ($map->reference_type === 'supplier') $refName = \App\Models\Supplier::find($map->reference_id)?->name ?? 'Deleted Supplier';

                                $scopeName = 'Unmapped';
                                if ($map->scope_type === 'company') $scopeName = \App\Models\Company::find($map->scope_id)?->name ?? 'Deleted Ministry';
                                if ($map->scope_type === 'location') $scopeName = \App\Models\Location::find($map->scope_id)?->name ?? 'Deleted Location';
                            @endphp
                            <tr>
                                <td style="padding-left: 15px;">
                                    <strong>{{ $refName }}</strong><br>
                                    <small class="text-muted">Type: {{ ucfirst($map->reference_type) }}</small>
                                </td>
                                <td style="vertical-align: middle;">
                                    <span class="label {{ $map->scope_type === 'company' ? 'bg-purple' : 'bg-blue' }}">
                                        <i class="fas {{ $map->scope_type === 'company' ? 'fa-university' : 'fa-map-marker-alt' }}"></i> 
                                        {{ $scopeName }}
                                    </span>
                                </td>
                                <td style="vertical-align: middle;" class="text-center">
                                    <form action="{{ route('gov.scope.mappings.destroy', $map->id) }}" method="POST" style="margin: 0;">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-danger" onclick="return confirm('Revoke this data isolation limit?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted" style="padding: 30px;">No private mapping rules found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="box-footer text-right" style="padding: 10px 15px 0 15px;">
                {{ $mappings->links() }}
            </div>
        </div>
    </div>
</div>

<!-- INLINE SLIDE MODAL FOR MAPPING FORM -->
<div class="modal fade" id="mappingModal" tabindex="-1" role="dialog" aria-labelledby="mappingModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h4 class="modal-title" id="mappingModalLabel"><i class="fas fa-user-lock text-blue"></i> Map Private Scoping Bounds</h4>
            </div>
            <form action="{{ route('gov.scope.mappings.store') }}" method="POST" style="margin-bottom: 0;">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>1. Choose Catalog Type</label>
                        <select name="reference_type" id="reference_type" class="form-control" required>
                            <option value="">-- Choose Type --</option>
                            <option value="category">Product Category</option>
                            <option value="model">Brand & Model</option>
                            <option value="manufacturer">Manufacturer</option>
                            <option value="supplier">Supplier & Vendor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>2. Select Specific Item</label>
                        <select name="reference_id" id="referenceSelector" class="form-control" required style="width: 100%;" disabled>
                            <option value="">-- Select type first --</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <label>3. Choose Scoping Level</label>
                        <select name="scope_type" id="scope_type" class="form-control" required>
                            <option value="">-- Choose Scope --</option>
                            <option value="company">Company (Ministry Scope)</option>
                            <option value="location">Location (Local Office Scope)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>4. Map Scoped Boundary Target</label>
                        <select name="scope_id" id="scopeSelector" class="form-control" required style="width: 100%;" disabled>
                            <option value="">-- Select scope level first --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default pull-left" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-lock"></i> Lock Reference</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    
    // Toggle SELECT2 logic
    $('#reference_type').on('change', function() {
        var val = $(this).val();
        if (val) {
            $('#referenceSelector').prop('disabled', false).val(null).trigger('change');
            initializeReferenceSelect2(val);
        } else {
            $('#referenceSelector').prop('disabled', true).val(null).trigger('change');
        }
    });

    $('#scope_type').on('change', function() {
        var val = $(this).val();
        if (val) {
            $('#scopeSelector').prop('disabled', false).val(null).trigger('change');
            initializeScopeSelect2(val);
        } else {
            $('#scopeSelector').prop('disabled', true).val(null).trigger('change');
        }
    });

    function initializeReferenceSelect2(type) {
        $('#referenceSelector').select2({
            dropdownParent: $('#mappingModal'),
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("gov.scope.reference-search") }}',
                dataType: 'json',
                delay: 200,
                data: function(params) {
                    return { q: params.term, type: type };
                },
                processResults: function(data) {
                    return { results: data };
                },
                cache: true
            },
            placeholder: "Search reference lists..."
        });
    }

    function initializeScopeSelect2(type) {
        $('#scopeSelector').select2({
            dropdownParent: $('#mappingModal'),
            minimumInputLength: 2,
            ajax: {
                url: '{{ route("gov.scope.tenant-search") }}',
                dataType: 'json',
                delay: 200,
                data: function(params) {
                    return { q: params.term, type: type };
                },
                processResults: function(data) {
                    return { results: data };
                },
                cache: true
            },
            placeholder: "Search boundary targets..."
        });
    }
});
</script>
@endsection