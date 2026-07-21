@extends('layouts/default')

@section('title', __('storeops::storeops.stock_register_dashboard'))

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="active"><a href="#consumables" data-toggle="tab"><i class="fa fa-tint"></i> {{ __('storeops::storeops.consumables_tab') }}</a></li>
                <li><a href="#accessories" data-toggle="tab"><i class="fa fa-keyboard-o"></i> {{ __('storeops::storeops.accessories_tab') }}</a></li>
                <li><a href="#components" data-toggle="tab"><i class="fa fa-microchip"></i> {{ __('storeops::storeops.components_tab') }}</a></li>
            </ul>
            
            <div class="tab-content">
                <!-- CONSUMABLES TAB -->
                <div class="tab-pane active" id="consumables">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover dataTable">
                            <thead>
                                <tr>
                                    <th>{{ __('storeops::storeops.item_name') }}</th>
                                    <th>Item Code</th>
                                    <th>Category</th>
                                    <th>{{ __('storeops::storeops.current_projected_qty') }}</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($consumables as $item)
                                    <tr>
                                        <td><strong>{{ $item->name }}</strong></td>
                                        <td>{{ $item->item_no ?? 'N/A' }}</td>
                                        <td>{{ $item->category->name ?? 'Consumable' }}</td>
                                        <td><span class="label label-info">{{ $item->qty }}</span></td>
                                        <td class="text-right">
                                            <a href="{{ route('storeops.register.kardex', ['type' => 'consumable', 'id' => $item->id]) }}" class="btn btn-sm btn-default">
                                                <i class="fa fa-book"></i> {{ __('storeops::storeops.view_stock_card') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">{{ __('storeops::storeops.no_consumables') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ACCESSORIES TAB -->
                <div class="tab-pane" id="accessories">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover dataTable">
                            <thead>
                                <tr>
                                    <th>{{ __('storeops::storeops.item_name') }}</th>
                                    <th>Category</th>
                                    <th>{{ __('storeops::storeops.current_projected_qty') }}</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($accessories as $item)
                                    <tr>
                                        <td><strong>{{ $item->name }}</strong></td>
                                        <td>{{ $item->category->name ?? 'Accessory' }}</td>
                                        <td><span class="label label-info">{{ $item->qty }}</span></td>
                                        <td class="text-right">
                                            <a href="{{ route('storeops.register.kardex', ['type' => 'accessory', 'id' => $item->id]) }}" class="btn btn-sm btn-default">
                                                <i class="fa fa-book"></i> {{ __('storeops::storeops.view_stock_card') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">{{ __('storeops::storeops.no_accessories') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- COMPONENTS TAB -->
                <div class="tab-pane" id="components">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover dataTable">
                            <thead>
                                <tr>
                                    <th>{{ __('storeops::storeops.item_name') }}</th>
                                    <th>Category</th>
                                    <th>{{ __('storeops::storeops.current_projected_qty') }}</th>
                                    <th class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($components as $item)
                                    <tr>
                                        <td><strong>{{ $item->name }}</strong></td>
                                        <td>{{ $item->category->name ?? 'Component' }}</td>
                                        <td><span class="label label-info">{{ $item->qty }}</span></td>
                                        <td class="text-right">
                                            <a href="{{ route('storeops.register.kardex', ['type' => 'component', 'id' => $item->id]) }}" class="btn btn-sm btn-default">
                                                <i class="fa fa-book"></i> {{ __('storeops::storeops.view_stock_card') }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">{{ __('storeops::storeops.no_components') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
