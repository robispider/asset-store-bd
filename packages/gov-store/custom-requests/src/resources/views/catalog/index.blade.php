@extends('layouts/default')

@section('title', 'Service Catalog')

@section('content')

{{-- Clean Employee Portal Styling --}}
<style>
    /* Sleek Search Header */
    .search-section {
        background: #fff;
        border: 1px solid #ddd;
        border-top: 3px solid var(--main-theme-color, #3c8dbc);
        padding: 15px 20px;
        border-radius: 4px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .search-section p {
        margin-bottom: 10px;
        font-size: 14px;
        font-weight: bold;
        color: #555;
    }
    .hero-search-wrapper input {
        border: 1px solid #ccc;
        padding: 12px 15px;
        font-size: 15px;
        border-radius: 4px;
        width: 100%;
        color: #333;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    .hero-search-wrapper input:focus {
        border-color: var(--main-theme-color, #3c8dbc);
        outline: 0;
        box-shadow: inset 0 1px 1px rgba(0,0,0,.075), 0 0 8px rgba(60, 141, 188, 0.6);
    }
    
    /* Quick Requests & Tidy Pipeline Layout */
    .dashboard-panel {
        min-height: 90px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 12px 15px;
        margin-bottom: 20px;
    }
    .dashboard-panel strong {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-size: 13px;
    }

    /* Quick Requests Button */
    .quick-request-btn {
        background: #f4f4f4;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px 12px;
        margin-right: 6px;
        margin-bottom: 6px;
        cursor: pointer;
        display: inline-block;
        font-size: 12px;
        color: #333;
        transition: all 0.15s;
    }
    .quick-request-btn:hover {
        background: var(--main-theme-color, #3c8dbc);
        color: white;
        border-color: var(--main-theme-color, #3c8dbc);
    }

    /* Tidy Pipeline Status Badges */
    .compact-pipeline-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 8px;
    }
    .compact-pipeline-card {
        background: #f9f9f9;
        border: 1px solid #e3e3e3;
        border-radius: 4px;
        padding: 6px 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s, border-color 0.15s;
    }
    .compact-pipeline-card:hover {
        background: #f1f1f1;
        border-color: #ccc;
    }
    .compact-pipeline-card .badge {
        font-size: 11px;
        padding: 4px 7px;
    }
    .compact-pipeline-card span.status-label {
        font-size: 12px;
        margin-left: 6px;
        color: #444;
        font-weight: 600;
    }
    
    /* Toolbar Controls */
    .control-bar {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 10px 15px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .view-toggles button {
        background: #f4f4f4;
        border: 1px solid #ddd;
        padding: 5px 10px;
        cursor: pointer;
        color: #555;
        margin-left: 5px;
    }
    .view-toggles button.active {
        background: var(--main-theme-color, #3c8dbc);
        color: white;
        border-color: var(--main-theme-color, #3c8dbc);
    }
    
    /* Product Card Base */
    .catalog-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        transition: box-shadow 0.2s;
        margin-bottom: 20px;
    }
    .catalog-card:hover {
        box-shadow: 0 3px 10px rgba(0,0,0,0.06);
    }
    
    /* DEFAULT: LIST VIEW */
    #catalogContainer.view-list .catalog-item { width: 100%; float: none; padding: 0 15px; }
    #catalogContainer.view-list .catalog-card { display: flex; flex-direction: row; align-items: stretch; text-align: left; }
    #catalogContainer.view-list .img-wrapper { width: 140px; padding: 10px; background: #fff; display: flex; align-items: center; justify-content: center; border-right: 1px solid #eee; flex-shrink: 0; }
    #catalogContainer.view-list .img-wrapper img { max-height: 85px; max-width: 100%; object-fit: contain; }
    #catalogContainer.view-list .card-body { flex-grow: 1; padding: 15px; display: flex; flex-direction: column; justify-content: center;}
    #catalogContainer.view-list .details-list { display: block; padding-left: 20px; margin-top: 5px; color: #666; font-size: 13px; }
    #catalogContainer.view-list .card-footer { width: 220px; padding: 15px; border-top: none; border-left: 1px solid #eee; background: #fafafa; display: flex; flex-direction: column; justify-content: center; align-items: center; flex-shrink: 0; }
    
    /* OPTIONAL: GRID VIEW */
    #catalogContainer.view-grid .catalog-item { width: 25%; float: left; padding: 0 10px; }
    #catalogContainer.view-grid .catalog-card { display: block; text-align: center; }
    #catalogContainer.view-grid .img-wrapper { height: 160px; padding: 15px; background: #f9f9f9; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid #eee; }
    #catalogContainer.view-grid .img-wrapper img { max-height: 100%; max-width: 100%; object-fit: contain; }
    #catalogContainer.view-grid .details-list { display: none; }
    #catalogContainer.view-grid .card-body { padding: 15px; }
    #catalogContainer.view-grid .card-footer { padding: 15px; border-top: 1px solid #eee; background: #fafafa; }
    
    /* Typography */
    .item-title { font-size: 15px; font-weight: bold; color: #333; margin-bottom: 3px; }
    #catalogContainer.view-grid .item-title { height: 38px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .item-category { font-size: 12px; color: #777; }

    @media (max-width: 991px) {
        #catalogContainer.view-grid .catalog-item { width: 50%; }
    }
    @media (max-width: 767px) {
        #catalogContainer.view-grid .catalog-item { width: 100%; }
        #catalogContainer.view-list .catalog-card { flex-direction: column; }
        #catalogContainer.view-list .img-wrapper, #catalogContainer.view-list .card-footer { width: 100%; border-right: none; border-left: none; border-top: 1px solid #eee;}
        .control-bar { flex-direction: column; align-items: stretch; }
        .control-bar > div { margin-bottom: 10px; }
    }
</style>

<!-- CLEAN HERO SECTION -->
<div class="row">
    <div class="col-md-12">
        <div class="search-section">
            <p>What equipment or supplies do you need to fulfill your tasks today?</p>
            <div class="hero-search-wrapper">
                <input type="text" id="catalogSearch" placeholder="Search for items, brands, or categories (e.g. 'Laptop', 'Mouse', 'Paper')...">
            </div>
        </div>
    </div>
</div>

<!-- SIDE-BY-SIDE: QUICK REQUESTS (LEFT) & COMPACT PIPELINE (RIGHT) -->
<div class="row">
    <!-- Quick Requests Panel -->
    <div class="col-md-7">
        <div class="dashboard-panel">
            <strong>Frequently Requested Items:</strong>
            <div class="quick-requests-container">
                <button class="quick-request-btn" data-search="laptop">💻 Laptop</button>
                <button class="quick-request-btn" data-search="mouse">🖱 Mouse</button>
                <button class="quick-request-btn" data-search="keyboard">⌨ Keyboard</button>
                <button class="quick-request-btn" data-search="toner">🖨 Toner</button>
                <button class="quick-request-btn" data-search="paper">📄 Paper</button>
                <button class="quick-request-btn" data-search="chair">🪑 Chair</button>
            </div>
        </div>
    </div>

    <!-- Tidy Tracking Pipeline Panel -->
    <div class="col-md-5">
        <div class="dashboard-panel">
            <strong>My Request Pipeline Status:</strong>
            <div class="compact-pipeline-wrapper">
                
                <!-- Pending -->
                <a href="{{ route('gov.requests.user.index') }}" style="text-decoration: none; flex: 1;">
                    <div class="compact-pipeline-card">
                        <span class="badge bg-yellow-active">{{ $pendingCount }}</span>
                        <span class="status-label">Pending</span>
                    </div>
                </a>

                <!-- Approved -->
                <a href="{{ route('gov.requests.user.index') }}" style="text-decoration: none; flex: 1;">
                    <div class="compact-pipeline-card">
                        <span class="badge bg-green-active">{{ $approvedCount }}</span>
                        <span class="status-label">Approved</span>
                    </div>
                </a>

                <!-- Rejected -->
                <a href="{{ route('gov.requests.user.index') }}" style="text-decoration: none; flex: 1;">
                    <div class="compact-pipeline-card">
                        <span class="badge bg-red-active">{{ $rejectedCount }}</span>
                        <span class="status-label">Rejected</span>
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
                <div><strong id="productCount">{{ $catalogItems->count() }}</strong> Items Available</div>
                
                <select id="catFilter" class="form-control input-sm" style="width: auto; display: inline-block;">
                    <option value="">All Categories</option>
                    @foreach($catalogItems->pluck('category')->unique()->sort() as $cat)
                        <option value="{{ strtolower($cat) }}">{{ $cat }}</option>
                    @endforeach
                </select>

                <select id="typeFilter" class="form-control input-sm" style="width: auto; display: inline-block;">
                    <option value="">All Groups</option>
                    <option value="asset">Hardware & Devices</option>
                    <option value="accessory">Accessories & Peripherals</option>
                    <option value="consumable">Supplies & Stationery</option>
                </select>
            </div>
            
            <!-- Right Controls: Sorting & View Grid/List toggling -->
            <div style="display: flex; align-items: center; gap: 10px;">
                <select id="sortOrder" class="form-control input-sm" style="width: auto; display: inline-block;">
                    <option value="name_asc">Sort: A-Z</option>
                    <option value="name_desc">Sort: Z-A</option>
                    <option value="avail_desc">Sort: Highest Stock</option>
                    <option value="date_desc">Sort: Newest First</option>
                </select>
                
                <div class="view-toggles">
                    <button id="btnList" class="active" title="List View (Recommended)"><i class="fas fa-list"></i></button>
                    <button id="btnGrid" title="Grid View"><i class="fas fa-th"></i></button>
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
                            <img src="{{ $item->image_url }}" alt="{{ $item->name }}">
                        </div>
                        
                        <div class="card-body">
                            <div class="item-title">{{ $item->name }}</div>
                            <div class="item-category">
                                <i class="fas fa-tag"></i> 
                                {{ $item->category }} &bull; 
                                {{ $item->type == 'Asset' ? 'Hardware' : ($item->type == 'Accessory' ? 'Accessory' : 'Supply') }}
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
                                    <span class="text-success"><i class="fas fa-check-circle"></i> In Stock</span>
                                @elseif($item->available_qty > 0)
                                    <span class="text-warning"><i class="fas fa-exclamation-triangle"></i> {{ $item->available_qty }} Remaining</span>
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
                    <h3 class="text-muted">No items available for request right now.</h3>
                </div>
            @endforelse
        </div>
    </div>
</div>

@endsection

@section('moar_scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. VIEW TOGGLERS (Default is List View) ---
    const btnGrid = document.getElementById('btnGrid');
    const btnList = document.getElementById('btnList');
    const container = document.getElementById('catalogContainer');

    btnGrid.addEventListener('click', () => {
        container.className = 'row view-grid';
        btnGrid.classList.add('active');
        btnList.classList.remove('active');
    });

    btnList.addEventListener('click', () => {
        container.className = 'row view-list';
        btnList.classList.add('active');
        btnGrid.classList.remove('active');
    });

    // --- 2. MULTI-FILTER LOGIC (Search, Category, Type) ---
    const searchInput = document.getElementById('catalogSearch');
    const catFilter = document.getElementById('catFilter');
    const typeFilter = document.getElementById('typeFilter');
    const items = document.querySelectorAll('.catalog-item');
    const countDisplay = document.getElementById('productCount');

    function applyFilters() {
        let term = searchInput.value.toLowerCase();
        let selectedCat = catFilter.value;
        let selectedType = typeFilter.value;
        let count = 0;

        items.forEach(item => {
            let name = item.dataset.name;
            let cat = item.dataset.category;
            let type = item.dataset.type;

            let matchesSearch = name.includes(term) || cat.includes(term);
            let matchesCat = !selectedCat || cat === selectedCat;
            let matchesType = !selectedType || type === selectedType;

            if (matchesSearch && matchesCat && matchesType) {
                item.style.display = 'block';
                count++;
            } else {
                item.style.display = 'none';
            }
        });

        countDisplay.innerText = count;
    }

    searchInput.addEventListener('input', applyFilters);
    catFilter.addEventListener('change', applyFilters);
    typeFilter.addEventListener('change', applyFilters);

    // --- 3. QUICK REQUEST CLICKS ---
    const quickBtns = document.querySelectorAll('.quick-request-btn');
    quickBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            let val = this.dataset.search;
            searchInput.value = val;
            applyFilters();
            
            searchInput.focus();
            searchInput.style.borderColor = '#3c8dbc';
            setTimeout(() => { searchInput.style.borderColor = '#ccc'; }, 600);
        });
    });

    // --- 4. TOP SORTING ---
    const sortSelect = document.getElementById('sortOrder');
    sortSelect.addEventListener('change', function() {
        let sortBy = this.value;
        let itemsArray = Array.from(items);

        itemsArray.sort((a, b) => {
            if (sortBy === 'name_asc') return a.dataset.name.localeCompare(b.dataset.name);
            if (sortBy === 'name_desc') return b.dataset.name.localeCompare(a.dataset.name);
            if (sortBy === 'avail_desc') return parseInt(b.dataset.avail) - parseInt(a.dataset.avail);
            if (sortBy === 'date_desc') return parseInt(b.dataset.date) - parseInt(a.dataset.date);
        });

        itemsArray.forEach(item => container.appendChild(item));
    });
});
</script>
@endsection