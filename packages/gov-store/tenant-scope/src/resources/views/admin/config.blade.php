@extends('layouts/default')

@section('title', __('tenantops::ops.config_title'))

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-sliders-h"></i> {{ __('tenantops::ops.config_header') }}</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">{{ __('tenantops::ops.config_description') }}</p>
            </div>
            
            <form action="{{ route('gov.scope.save-strategy') }}" method="POST">
                @csrf
                <div class="box-body table-responsive" style="padding: 0;">
                    <table class="table table-striped table-hover" style="margin-bottom: 0;">
                        <thead>
                            <tr style="background-color: #fafafa;">
                                <th style="padding-left: 20px;">{{ __('tenantops::ops.label_catalog_type') }}</th>
                                <th style="width: 300px;">{{ __('tenantops::ops.label_isolation_boundary') }}</th>
                                <th style="width: 150px;" class="text-center">{{ __('tenantops::ops.label_show_only_used') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $types = [
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
                                    <td style="vertical-align: middle; padding-left: 20px;"><strong>{{ $label }}</strong></td>
                                    <td style="vertical-align: middle;">
                                        <select name="strategies[{{ $key }}][strategy]" class="form-control input-sm">
                                            <option value="global" {{ $strategy === 'global' ? 'selected' : '' }}>{{ __('tenantops::ops.strategy_global') }}</option>
                                            <option value="company" {{ $strategy === 'company' ? 'selected' : '' }}>{{ __('tenantops::ops.strategy_company') }}</option>
                                            <option value="location" {{ $strategy === 'location' ? 'selected' : '' }}>{{ __('tenantops::ops.strategy_location') }}</option>
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
                <div class="box-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> {{ __('tenantops::ops.btn_save_policies') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection