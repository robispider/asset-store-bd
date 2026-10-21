@extends('layouts/default')
@section('title', __('govtracking::general.ui.extra_assign_legacy_assets', ['title' => $initiative->title]))

@section('content')
@php
    $u = 'govtracking::general.ui.';
@endphp
<div class="gs-tracking-form-page gs-tracking-form-page--wide">
    <x-gs::page-header :title="__($u.'retrospective_tagging_console')" :subtitle="$initiative->title" />

    <x-gs::alert tone="warning">
        <p>{{ __($u.'search_for_historical_assets_already_present_in_snipe_it_inventory_and_link_them_to_an_active_tracking_code_under_this_initiative') }}</p>
    </x-gs::alert>

    @include('govtracking::tracking_codes.partials._errors')

    <form method="GET" action="{{ route('gov.tracking.initiatives.retrospective.index', $initiative->id) }}">
        <input type="hidden" name="search_trigger" value="1">
        <x-gs::box :title="__($u.'search_legacy_inventory_parameters')" icon="fas fa-filter">
            <div class="row">
                <div class="col-md-3 form-group">
                    <label for="retro_category">{{ __($u.'category') }}</label>
                    <select name="category_id" id="retro_category" class="form-control select2">
                        <option value="">{{ __($u.'all_categories') }}</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="retro_manufacturer">{{ __($u.'manufacturer') }}</label>
                    <select name="manufacturer_id" id="retro_manufacturer" class="form-control select2">
                        <option value="">{{ __($u.'all_manufacturers') }}</option>
                        @foreach($manufacturers as $manufacturer)
                            <option value="{{ $manufacturer->id }}" @selected(request('manufacturer_id') == $manufacturer->id)>{{ $manufacturer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="retro_supplier">{{ __($u.'supplier') }}</label>
                    <select name="supplier_id" id="retro_supplier" class="form-control select2">
                        <option value="">{{ __($u.'all_suppliers') }}</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(request('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label for="retro_purchase_start">{{ __($u.'purchase_date_range') }}</label>
                    <div class="input-group">
                        <input type="date" name="purchase_start" id="retro_purchase_start" class="form-control" value="{{ request('purchase_start') }}">
                        <span class="input-group-addon">{{ __($u.'extra_to') }}</span>
                        <input type="date" name="purchase_end" class="form-control" value="{{ request('purchase_end') }}" aria-label="{{ __($u.'purchase_date_range') }} — {{ __($u.'extra_to') }}">
                    </div>
                </div>
            </div>
            <x-slot:footer>
                <div class="gs-tracking-box-actions">
                    <x-gs::button tone="secondary" outline :href="route('gov.tracking.initiatives.manage', $initiative->id)">{{ __('govtracking::general.task.back') }}</x-gs::button>
                    <x-gs::button type="submit" icon="fa-magnifying-glass">{{ __($u.'find_assets') }}</x-gs::button>
                </div>
            </x-slot:footer>
        </x-gs::box>
    </form>

    @if($searched)
        <form action="{{ route('gov.tracking.initiatives.retrospective.associate', $initiative->id) }}" method="POST">
            @csrf
            <x-gs::box :title="__($u.'query_results_showing_up_to_500_items')">
                @if($assets->count() > 0)
                    <x-slot:tools>
                        <label class="gs-sr-only" for="retro_tracking_code">{{ __($u.'select_target_tracking_code') }}</label>
                        <select name="tracking_code_id" id="retro_tracking_code" class="form-control input-sm gs-tracking-tool-select" required>
                            <option value="">{{ __($u.'select_target_tracking_code') }}</option>
                            @foreach($trackingCodes as $code)
                                <option value="{{ $code->id }}">{{ $code->tracking_code }} ({{ $code->task_title }})</option>
                            @endforeach
                        </select>
                        <x-gs::button type="submit" tone="success" size="sm" icon="fa-link" id="bulk-submit" disabled>{{ __($u.'link_selected_assets') }}</x-gs::button>
                    </x-slot:tools>
                @endif
                <x-gs::table>
                    <thead>
                        <tr>
                            <th scope="col" class="gs-tracking-check-cell"><input type="checkbox" id="select-all-trigger" aria-label="{{ __($u.'select_all_assets') }}"></th>
                            <th scope="col">{{ __($u.'asset_tag') }}</th>
                            <th scope="col">{{ __($u.'name') }}</th>
                            <th scope="col">{{ __($u.'category') }}</th>
                            <th scope="col">{{ __($u.'manufacturer') }}</th>
                            <th scope="col">{{ __($u.'purchase_date') }}</th>
                            <th scope="col">{{ __($u.'tag_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $asset)
                            <tr>
                                <td class="gs-tracking-check-cell">
                                    <input type="checkbox" name="asset_ids[]" value="{{ $asset->id }}" class="asset-checkbox" aria-label="{{ $asset->asset_tag }}" @disabled($asset->is_already_tagged)>
                                </td>
                                <td><code>{{ $asset->asset_tag }}</code></td>
                                <td>{{ $asset->name }}</td>
                                <td>{{ $asset->model->category->name ?? __($u.'not_recorded') }}</td>
                                <td>{{ $asset->model->manufacturer->name ?? __($u.'not_recorded') }}</td>
                                <td>{{ $asset->purchase_date }}</td>
                                <td>
                                    @if($asset->is_already_tagged)
                                        <x-gs::badge tone="warning"><i class="fas fa-check" aria-hidden="true"></i> {{ __($u.'already_tagged') }}</x-gs::badge>
                                    @else
                                        <span class="gs-tracking-muted">{{ __($u.'available') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="gs-table__empty">{{ __($u.'no_assets_found_matching_specified_parameters') }}</td></tr>
                        @endforelse
                    </tbody>
                </x-gs::table>
            </x-gs::box>
        </form>
    @endif
</div>
@stop
