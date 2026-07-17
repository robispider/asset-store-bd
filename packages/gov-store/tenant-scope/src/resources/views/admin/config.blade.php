@extends('layouts/default')

@section('title', 'Global Scoping Policies')

@section('content')
<div class="row">
    <div class="col-md-10 col-md-offset-1">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-sliders-h"></i> Reference Scoping Strategy Policies</h3>
                <p class="text-muted" style="margin-top: 5px; margin-bottom: 0;">Specify which spatial or corporate boundary limits apply to each catalog data model.</p>
            </div>
            
            <form action="{{ route('gov.scope.save-strategy') }}" method="POST">
                @csrf
                <div class="box-body table-responsive" style="padding: 0;">
                    <table class="table table-striped table-hover" style="margin-bottom: 0;">
                        <thead>
                            <tr style="background-color: #fafafa;">
                                <th style="padding-left: 20px;">Catalog Reference Type</th>
                                <th style="width: 300px;">Isolation Boundary</th>
                                <th style="width: 150px;" class="text-center">"Show Only Used"</th>
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
                                            <option value="global" {{ $strategy === 'global' ? 'selected' : '' }}>🌎 Global (Shared by all)</option>
                                            <option value="company" {{ $strategy === 'company' ? 'selected' : '' }}>🏛 Company (Ministry scoped)</option>
                                            <option value="location" {{ $strategy === 'location' ? 'selected' : '' }}>📍 Office (Local building scoped)</option>
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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Scoping Policies</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection