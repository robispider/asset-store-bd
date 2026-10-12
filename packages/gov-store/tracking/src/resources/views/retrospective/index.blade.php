@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_assign_legacy_assets') . $initiative->title)

@section('content')
<div class="row">
    <!-- Filter Bar Card -->
    <div class="col-md-12">
        <div class="callout callout-warning">
            <h4><i class="fa fa-history"></i> {{ __('govtracking::general.ui.retrospective_tagging_console') }}</h4>
            <p>{{ __('govtracking::general.ui.search_for_historical_assets_already_present_in_snipe_it_inventory_and_link_them_to_an_active_tracking_code_under_this_initiative') }}</p>
        </div>

        <form method="GET" action="{{ route('gov.tracking.initiatives.retrospective.index', $initiative->id) }}">
            <input type="hidden" name="search_trigger" value="1">
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-filter"></i> {{ __('govtracking::general.ui.search_legacy_inventory_parameters') }}</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>{{ __('govtracking::general.ui.category') }}</label>
                            <select name="category_id" class="form-control select2">
                                <option value="">{{ __('govtracking::general.ui.all_categories') }}</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>{{ __('govtracking::general.ui.manufacturer') }}</label>
                            <select name="manufacturer_id" class="form-control select2">
                                <option value="">{{ __('govtracking::general.ui.all_manufacturers') }}</option>
                                @foreach($manufacturers as $manufacturer)
                                    <option value="{{ $manufacturer->id }}" {{ request('manufacturer_id') == $manufacturer->id ? 'selected' : '' }}>{{ $manufacturer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>{{ __('govtracking::general.ui.supplier') }}</label>
                            <select name="supplier_id" class="form-control select2">
                                <option value="">{{ __('govtracking::general.ui.all_suppliers') }}</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>{{ __('govtracking::general.ui.purchase_date_range') }}</label>
                            <div class="input-group">
                                <input type="date" name="purchase_start" class="form-control" value="{{ request('purchase_start') }}">
                                <span class="input-group-addon">{{ __('govtracking::general.ui.extra_to') }}</span>
                                <input type="date" name="purchase_end" class="form-control" value="{{ request('purchase_end') }}">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer text-right">
                    <a href="{{ route('gov.tracking.initiatives.show', $initiative->id) }}" class="btn btn-default">{{ __('govtracking::general.task.back') }}</a>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> {{ __('govtracking::general.ui.find_assets') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if($searched)
<div class="row">
    <div class="col-md-12">
        <form action="{{ route('gov.tracking.initiatives.retrospective.associate', $initiative->id) }}" method="POST">
            @csrf
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">{{ __('govtracking::general.ui.query_results_showing_up_to_500_items') }}</h3>

                    @if($assets->count() > 0)
                        <div class="pull-right form-inline">
                            <select name="tracking_code_id" class="form-control input-sm" required style="width: 250px;">
                                <option value="">{{ __('govtracking::general.ui.select_target_tracking_code') }}</option>
                                @foreach($trackingCodes as $code)
                                    <option value="{{ $code->id }}">{{ $code->tracking_code }} ({{ $code->task_title }})</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-success btn-sm" id="bulk-submit" disabled>
                                <i class="fa fa-link"></i> {{ __('govtracking::general.ui.link_selected_assets') }}
                            </button>
                        </div>
                    @endif
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th width="30" class="text-center"><input type="checkbox" id="select-all-trigger"></th>
                                <th>{{ __('govtracking::general.ui.asset_tag') }}</th>
                                <th>{{ __('govtracking::general.ui.name') }}</th>
                                <th>{{ __('govtracking::general.ui.category') }}</th>
                                <th>{{ __('govtracking::general.ui.manufacturer') }}</th>
                                <th>{{ __('govtracking::general.ui.purchase_date') }}</th>
                                <th>{{ __('govtracking::general.ui.tag_status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($assets as $asset)
                                <tr>
                                    <td class="text-center">
                                        <input type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" class="asset-checkbox" {{ $asset->is_already_tagged ? 'disabled' : '' }}>
                                    </td>
                                    <td><code>{{ $asset->asset_tag }}</code></td>
                                    <td>{{ $asset->name }}</td>
                                    <td>{{ $asset->model->category->name ?? 'N/A' }}</td>
                                    <td>{{ $asset->model->manufacturer->name ?? 'N/A' }}</td>
                                    <td>{{ $asset->purchase_date }}</td>
                                    <td>
                                        @if($asset->is_already_tagged)
                                            <span class="label label-warning"><i class="fa fa-check"></i> {{ __('govtracking::general.ui.already_tagged') }}</span>
                                        @else
                                            <span class="text-muted">{{ __('govtracking::general.ui.available') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">{{ __('govtracking::general.ui.no_assets_found_matching_specified_parameters') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>
</div>


@endif
@stop
