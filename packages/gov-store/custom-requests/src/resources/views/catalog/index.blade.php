@extends('layouts/default')

@section('title', 'Item Catalog')

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="active"><a href="#tab_consumables" data-toggle="tab"><i class="fas fa-tint"></i> Consumables <span class="badge">{{ $consumables->count() }}</span></a></li>
                <li><a href="#tab_accessories" data-toggle="tab"><i class="fas fa-keyboard"></i> Accessories <span class="badge">{{ $accessories->count() }}</span></a></li>
                <li><a href="#tab_assets" data-toggle="tab"><i class="fas fa-laptop"></i> Hardware (Assets) <span class="badge">{{ $assets->count() }}</span></a></li>
            </ul>
            
            <div class="tab-content">
                <!-- CONSUMABLES TAB -->
                <div class="tab-pane active" id="tab_consumables">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>Name</th><th>Category</th><th>Available Qty</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($consumables as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->category->name ?? 'N/A' }}</td>
                                <td>{{ $item->numRemaining() }}</td>
                                <td>
                                    @include('govstore::components.request-button', [
                                        'itemType' => 'Consumable', 'itemId' => $item->id, 'itemName' => $item->name
                                    ])
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- ACCESSORIES TAB -->
                <div class="tab-pane" id="tab_accessories">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>Name</th><th>Category</th><th>Available Qty</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($accessories as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->category->name ?? 'N/A' }}</td>
                                <td>{{ $item->numRemaining() }}</td>
                                <td>
                                    @include('govstore::components.request-button', [
                                        'itemType' => 'Accessory', 'itemId' => $item->id, 'itemName' => $item->name
                                    ])
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- ASSETS TAB -->
                <div class="tab-pane" id="tab_assets">
                    <table class="table table-striped table-hover">
                        <thead><tr><th>Asset Tag</th><th>Model</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach($assets as $item)
                            <tr>
                                <td>{{ $item->asset_tag }}</td>
                                <td>{{ $item->model->name ?? 'N/A' }}</td>
                                <td>
                                    @include('govstore::components.request-button', [
                                        'itemType' => 'Asset', 'itemId' => $item->id, 'itemName' => $item->present()->name ?: $item->asset_tag
                                    ])
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection