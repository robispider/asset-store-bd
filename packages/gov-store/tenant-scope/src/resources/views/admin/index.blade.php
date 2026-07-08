@extends('layouts/default')

@section('title', 'Tenant Scoping & Data Isolation')

@section('content')
<div class="row">
    <!-- LEFT: Core Configurator Strategy Matrix -->
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-sliders-h"></i> Reference Scoping Strategy Policies</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">Specify which spatial or corporate boundary limits apply to each catalog data model.</p>
            </div>
            
            <form action="{{ route('gov.scope.save-strategy') }}" method="POST">
                @csrf
                <div class="box-body table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Catalog Reference Type</th>
                                <th style="width: 250px;">Isolation Boundary</th>
                                <th style="width: 150px;" class="text-center">"Show Only Used"</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $types = [
                                    'categories'    => 'Product Categories',
                                    'models'        => 'Device Brands & Models',
                                    'manufacturers' => 'Manufacturers',
                                    'suppliers'     => 'Suppliers & Vendors',
                                    'fieldsets'     => 'Custom Fieldsets',
                                    'locations'     => 'Office Location Trees'
                                ];
                            @endphp

                            @foreach($types as $key => $label)
                                @php
                                    $cfg = $configs[$key] ?? null;
                                    $strategy = $cfg ? $cfg->scope_strategy : 'global';
                                    $showUsed = $cfg ? $cfg->show_only_used : false;
                                @endphp
                                <tr>
                                    <td style="vertical-align: middle;"><strong>{{ $label }}</strong></td>
                                    <td style="vertical-align: middle;">
                                        <select name="strategies[{{ $key }}][strategy]" class="form-control input-sm">
                                            <option value="global" {{ $strategy === 'global' ? 'selected' : '' }}>🌎 Global (Shared by all)</option>
                                            <option value="company" {{ $strategy === 'company' ? 'selected' : '' }}>🏛 Company (Ministry scoped)</option>
                                            <option value="office" {{ $strategy === 'office' ? 'selected' : '' }}>📍 Office (Local building scoped)</option>
                                        </select>
                                    </td>
                                    <td style="vertical-align: middle;" class="text-center">
                                        <input type="checkbox" name="strategies[{{ $key }}][show_only_used]" value="1" {{ $showUsed ? 'checked' : '' }}>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-primary pull-right"><i class="fas fa-save"></i> Save Scoping Policies</button>
                </div>
            </form>
        </div>

        <!-- ACTIVE POLYS MAPPING REGISTRY -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-lock"></i> Explicit Scoping Boundaries Map ({{ $mappings->count() }})</h3>
            </div>
            <div class="box-body table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Reference Item</th>
                            <th>Scoped Scope Boundary</th>
                            <th style="width: 80px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mappings as $map)
                            @php
                                // Exception safe lookups
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
                                <td>
                                    <strong>{{ $refName }}</strong><br>
                                    <small class="text-muted">Type: {{ ucfirst($map->reference_type) }}</small>
                                </td>
                                <td style="vertical-align: middle;">
                                    <span class="label {{ $map->scope_type === 'company' ? 'bg-purple' : 'bg-blue' }}">
                                        <i class="fas {{ $map->scope_type === 'company' ? 'fa-university' : 'fa-map-marker-alt' }}"></i> 
                                        {{ $scopeName }}
                                    </span>
                                </td>
                                <td style="vertical-align: middle;">
                                    <form action="{{ route('gov.scope.mappings.destroy', $map->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-danger btn-block" onclick="return confirm('Revoke this data isolation limit?')">
                                            <i class="fas fa-trash"></i> Revoke
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted" style="padding: 20px;">No private boundaries mapped yet. All items default to Global.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT: Map Polymorphic References form -->
    <div class="col-md-5">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-lock"></i> Map Private Scoping Bounds</h3>
            </div>
            <form action="{{ route('gov.scope.mappings.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    
                    <div class="form-group">
                        <label for="reference_type">1. Choose Catalog Type</label>
                        <select name="reference_type" id="reference_type" class="form-control" required>
                            <option value="">-- Choose Type --</option>
                            <option value="category">Product Category</option>
                            <option value="model">Brand & Model</option>
                            <option value="manufacturer">Manufacturer</option>
                            <option value="supplier">Supplier & Vendor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="referenceSelector">2. Select Specific Item</label>
                        <select name="reference_id" id="referenceSelector" class="form-control" required style="width: 100%;" disabled>
                            <option value="">-- Select type first --</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 25px;">
                        <label for="scope_type">3. Choose Scoping Level</label>
                        <select name="scope_type" id="scope_type" class="form-control" required>
                            <option value="">-- Choose Scope --</option>
                            <option value="company">Company (Ministry Scope)</option>
                            <option value="location">Location (Local Office Scope)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="scopeSelector">4. Map Scoped Boundary Target</label>
                        <select name="scope_id" id="scopeSelector" class="form-control" required style="width: 100%;" disabled>
                            <option value="">-- Select scope level first --</option>
                        </select>
                    </div>

                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success btn-block"><i class="fas fa-lock"></i> Lock Reference to Boundary</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
<script>
$(document).ready(function() {
    
    // 1. Dynamic Toggle triggers for selectors
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

    // 2. High-Performance AJAX Reference searching
    function initializeReferenceSelect2(type) {
        $('#referenceSelector').select2({
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

    // 3. High-Performance AJAX Scope target searching
    function initializeScopeSelect2(type) {
        $('#scopeSelector').select2({
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