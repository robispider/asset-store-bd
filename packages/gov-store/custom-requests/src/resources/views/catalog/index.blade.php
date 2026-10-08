@extends('layouts/default')

@section('title', __('requestlabels::requests.catalog_title'))

@section('content')
<div class="cr-theme">

{{-- Clean Employee Portal Styling --}}


<!-- CLEAN HERO SECTION -->
<div class="row">
    <div class="col-md-12">
        <div class="search-section">
            <p>{{ __('requestlabels::requests.catalog_hero_question') }}</p>
            <div class="hero-search-wrapper">
                <input type="text" id="catalogSearch" value="{{ request('q') }}" placeholder="{{ __('requestlabels::requests.catalog_search_placeholder') }}">
            </div>
        </div>
    </div>
</div>

<!-- SIDE-BY-SIDE: QUICK REQUESTS (LEFT) & COMPACT PIPELINE (RIGHT) -->
<div class="row">
    <!-- Quick Requests Panel -->
    <div class="col-md-7">
        <div class="dashboard-panel">
            <strong>{{ __('requestlabels::requests.catalog_quick_requests_label') }}</strong>
            <div class="quick-requests-container">
                <button class="quick-request-btn" data-search="laptop">💻 {{ __('requestlabels::requests.quick_laptop') }}</button>
                <button class="quick-request-btn" data-search="mouse">🖱 {{ __('requestlabels::requests.quick_mouse') }}</button>
                <button class="quick-request-btn" data-search="keyboard">⌨ {{ __('requestlabels::requests.quick_keyboard') }}</button>
                <button class="quick-request-btn" data-search="toner">🖨 {{ __('requestlabels::requests.quick_toner') }}</button>
                <button class="quick-request-btn" data-search="paper">📄 {{ __('requestlabels::requests.quick_paper') }}</button>
                <button class="quick-request-btn" data-search="chair">🪑 {{ __('requestlabels::requests.quick_chair') }}</button>
            </div>
        </div>
    </div>

    <!-- Tidy Tracking Pipeline Panel -->
    <div class="col-md-5">
        <div class="dashboard-panel">
            <strong>{{ __('requestlabels::requests.pipeline') }}</strong>
            <div class="compact-pipeline-wrapper">
                
                <!-- Pending -->
                <a href="{{ route('gov.requests.user.index') }}" style="text-decoration: none; flex: 1;">
                    <div class="compact-pipeline-card">
                        <span class="badge bg-yellow-active">{{ $pendingCount }}</span>
                        <span class="status-label">{{ __('requestlabels::requests.catalog_pipeline_pending_label') }}</span>
                    </div>
                </a>

                <!-- Approved -->
                <a href="{{ route('gov.requests.user.index') }}" style="text-decoration: none; flex: 1;">
                    <div class="compact-pipeline-card">
                        <span class="badge bg-green-active">{{ $approvedCount }}</span>
                        <span class="status-label">{{ __('requestlabels::requests.catalog_pipeline_approved_label') }}</span>
                    </div>
                </a>

                <!-- Rejected -->
                <a href="{{ route('gov.requests.user.index') }}" style="text-decoration: none; flex: 1;">
                    <div class="compact-pipeline-card">
                        <span class="badge bg-red-active">{{ $rejectedCount }}</span>
                        <span class="status-label">{{ __('requestlabels::requests.catalog_pipeline_rejected_label') }}</span>
                    </div>
                </a>

            </div>
        </div>
    </div>
</div>

<!-- TOP FILTER BAR & CONTROL BAR -->
<div class="row">
    <div class="col-md-12">
        <div class="control-bar">
            <!-- Left Controls: Dynamic Filtering -->
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <div><strong id="productCount">{{ $catalogItems->total() }}</strong> {{ __('requestlabels::requests.items_available') }}</div>
                
                <select id="catFilter" class="form-control input-sm" aria-label="{{ __('requestlabels::requests.all_categories') }}" style="width:auto">
    <option value="">{{ __('requestlabels::requests.all_categories') }}</option>
    @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
        <option value="{{ $cat->id }}" {{ (int) request('category_id') === (int) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
    @endforeach
</select>

                <select id="typeFilter" aria-label="{{ __('requestlabels::requests.item_type') }}" class="form-control input-sm" style="width: auto; display: inline-block;">
                    <option value="">{{ __('requestlabels::requests.all_groups') }}</option>
                    <option value="asset_model" {{ request('type') === 'asset_model' ? 'selected' : '' }}>{{ __('requestlabels::requests.type_asset_model') }}</option>
                    <option value="accessory" {{ request('type') === 'accessory' ? 'selected' : '' }}>{{ __('requestlabels::requests.type_accessory') }}</option>
                    <option value="consumable" {{ request('type') === 'consumable' ? 'selected' : '' }}>{{ __('requestlabels::requests.type_consumable') }}</option>
                </select>
            </div>
            
            <!-- Right Controls: Sorting & View Grid/List toggling -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <button id="catalogFilterButton" class="btn btn-primary btn-sm">{{ __('requestlabels::requests.search') }}</button>
                
                <div class="view-toggles">
                    <button id="btnList" class="active" aria-pressed="true" aria-label="{{ __('requestlabels::requests.catalog_view_list_label') }}" title="{{ __('requestlabels::requests.catalog_view_list_label') }}"><i class="fas fa-list" aria-hidden="true"></i></button>
                    <button id="btnGrid" aria-pressed="false" aria-label="{{ __('requestlabels::requests.catalog_view_grid_label') }}" title="{{ __('requestlabels::requests.catalog_view_grid_label') }}"><i class="fas fa-th" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PRODUCT GRID (Toggles class between view-list and view-grid) -->
<div class="row">
    <div class="col-md-12">
        <div id="catalogContainer" class="row view-list">
            @forelse($catalogItems as $item)
                <div class="catalog-item" 
                     data-name="{{ strtolower($item->name) }}" 
                     data-type="{{ strtolower($item->type) }}"
                     data-category="{{ strtolower($item->category) }}"
                     data-avail="{{ $item->available_qty }}"
                     data-date="{{ $item->created_timestamp }}">
                    
                    <div class="catalog-card">
                        <div class="img-wrapper">
                            <img class="catalog-image" src="{{ $item->image_url }}" alt="{{ $item->name }}">
                            <i class="fas fa-box catalog-image-fallback" aria-hidden="true"></i>
                        </div>
                        
                        <div class="card-body">
                            <div class="item-title">{{ $item->name }}</div>
                            <div class="item-category">
                                <i class="fas fa-tag"></i> 
                                {{ $item->category }} &bull; 
                                {{ __('requestlabels::requests.type_'.$item->type) }}
                            </div>
                            
                            <ul class="details-list">
                                @foreach($item->details as $detail)
                                    <li>{{ $detail }}</li>
                                @endforeach
                            </ul>
                        </div>
                        
                        <div class="card-footer">
                            <div style="font-size: 13px; margin-bottom: 12px; font-weight: bold;">
                                @if($item->available_qty > 5)
                                    <span class="text-success"><i class="fas fa-check-circle"></i> {{ $item->available_qty }} {{ __('requestlabels::requests.in_stock') }}</span>
                                @elseif($item->available_qty > 0)
                                    <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> {{ $item->available_qty }} {{ __('requestlabels::requests.remaining') }}</span>
                                @endif
                            </div>
                            
                            @include('govstore::components.request-button', [
                                'itemType' => $item->type, 
                                'itemId' => $item->id, 
                                'itemName' => $item->name
                            ])
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-md-12 text-center" style="padding: 50px;">
                    <i class="fas fa-box-open fa-3x text-muted"></i>
                    <h3 class="text-muted">{{ __('requestlabels::requests.catalog_empty_state_title') }}</h3>
                    <p class="text-muted">{{ __('requestlabels::requests.catalog_empty_state_subtitle') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{ $catalogItems->links() }}
</div>
@endsection