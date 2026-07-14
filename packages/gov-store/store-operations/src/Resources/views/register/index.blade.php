@extends('layouts/default')

@section('title', 'Stock Register Dashboard')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="active"><a href="#consumables" data-toggle="tab"><i class="fa fa-tint"></i> Consumables</a></li>
                <li><a href="#accessories" data-toggle="tab"><i class="fa fa-keyboard-o"></i> Accessories</a></li>
                <li><a href="#components" data-toggle="tab"><i class="fa fa-microchip"></i> Components</a></li>
            </ul>
            
            <div class="tab-content">
                <!-- CONSUMABLES TAB -->
                <div class="tab-pane active" id="consumables">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover dataTable">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Item Code</th>
                                    <th>Category</th>
                                    <th>Current Projected Qty</th>
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
                                                <i class="fa fa-book"></i> View Stock Card (Kardex)
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted">No consumables found in this warehouse.</td></tr>
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
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Current Projected Qty</th>
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
                                                <i class="fa fa-book"></i> View Stock Card (Kardex)
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">No accessories found in this warehouse.</td></tr>
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
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th>Current Projected Qty</th>
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
                                                <i class="fa fa-book"></i> View Stock Card (Kardex)
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">No components found in this warehouse.</td></tr>
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
