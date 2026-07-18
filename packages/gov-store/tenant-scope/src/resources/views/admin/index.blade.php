@extends('layouts/default')

@section('title', __('tenantops::ops.index_title'))

@section('content')
<div class="row">
    <!-- LEFT: Core Configurator Strategy Matrix -->
    <div class="col-md-7">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-sliders-h"></i> {{ __('tenantops::ops.index_header') }}</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">{{ __('tenantops::ops.index_description') }}</p>
            </div>
            
            <form action="{{ route('gov.scope.save-strategy') }}" method="POST">
                @csrf
                <div class="box-body table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('tenantops::ops.label_catalog_type') }}</th>
                                <th style="width: 250px;">{{ __('tenantops::ops.label_isolation_boundary') }}</th>
                                <th style="width: 150px;" class="text-center">{{ __('tenantops::ops.label_show_only_used') }}</th>
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
                                            <option value="global" {{ $strategy === 'global' ? 'selected' : '' }}>{{ __('tenantops::ops.strategy_global') }}</option>
                                            <option value="company" {{ $strategy === 'company' ? 'selected' : '' }}>{{ __('tenantops::ops.strategy_company') }}</option>
                                            <option value="office" {{ $strategy === 'office' ? 'selected' : '' }}>{{ __('tenantops::ops.strategy_location') }}</option>
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
                    <button type="submit" class="btn btn-primary pull-right"><i class="fas fa-save"></i> {{ __('tenantops::ops.btn_save_policies') }}</button>
                </div>
            </form>
        </div>

        <!-- ACTIVE POLYS MAPPING REGISTRY -->
        <div class="box box-default">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-user-lock"></i> {{ __('tenantops::ops.index_explicit_map') }} ({{ $mappings->count() }})</h3>
            </div>
            <div class="box-body table-responsive" style="max-height: 350px; overflow-y: auto;">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>{{ __('tenantops::ops.table_reference_item') }}</th>
                            <th>{{ __('tenantops::ops.table_scoped_boundary') }}</th>
                            <th style="width: 80px;">{{ __('tenantops::ops.table_action') }}</th>
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
                                    <small class="text-muted">{{ __('tenantops::ops.label_type') }} {{ ucfirst($map->reference_type) }}</small>
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
                                        <button type="submit" class="btn btn-xs btn-danger btn-block" onclick="return confirm('{{ __('tenantops::ops.confirm_revoke') }}')">
                                            <i class="fas fa-trash"></i> {{ __('tenantops::ops.btn_delete') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted" style="padding: 20px;">{{ __('tenantops::ops.index_empty_boundaries') }}</td>
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
                <h3 class="box-title"><i class="fas fa-user-lock"></i> {{ __('tenantops::ops.index_map_header') }}</h3>
            </div>
            <form action="{{ route('gov.scope.mappings.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    
                    <div class="form-group">
                        <label for="reference_type">{{ __('tenantops::ops.modal_step1_label') }}</label>
                        <select name="reference_type" id="reference_type" class="form-control" required>
                            <option value="">{{ __('tenantops::ops.modal_step1_placeholder') }}</option>
                            <option value="category">Product Category</option>
                            <option value="model">Brand & Model</option>
                            <option value="manufacturer">Manufacturer</option>
                            <option value="supplier">Supplier & Vendor</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="referenceSelector">{{ __('tenantops::ops.modal_step2_label') }}</label>
                        <select name="reference_id" id="referenceSelector" class="form-control" required style="width: 100%;" disabled>
                            <option value="">{{ __('tenantops::ops.modal_step2_placeholder') }}</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 25px;">
                        <label for="scope_type">{{ __('tenantops::ops.modal_step3_label') }}</label>
                        <select name="scope_type" id="scope_type" class="form-control" required>
                            <option value="">{{ __('tenantops::ops.modal_step3_placeholder') }}</option>
                            <option value="company">Company (Ministry Scope)</option>
                            <option value="location">Location (Local Office Scope)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="scopeSelector">{{ __('tenantops::ops.modal_step4_label') }}</label>
                        <select name="scope_id" id="scopeSelector" class="form-control" required style="width: 100%;" disabled>
                            <option value="">{{ __('tenantops::ops.modal_step4_placeholder') }}</option>
                        </select>
                    </div>

                </div>
                <div class="box-footer">
                    <button type="submit" class="btn btn-success btn-block"><i class="fas fa-lock"></i> {{ __('tenantops::ops.index_btn_lock') }}</button>
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