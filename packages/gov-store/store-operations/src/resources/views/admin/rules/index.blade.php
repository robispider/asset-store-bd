@extends('layouts/default')
@section('title', __('storeops::rules.studio'))

@section('content')
<style>
    /* Rule Studio Layout Grid */
    .studio-container {
        display: grid;
        grid-template-columns: 290px 1fr;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        min-height: 750px;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    /* Left Pane: Sidebar Explorer */
    .studio-sidebar {
        background: #f8fafc;
        border-right: 1px solid #e2e8f0;
        display: flex;
        flex-direction: column;
    }

    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid #e2e8f0;
        cursor: pointer;
    }

    .sidebar-search {
        padding: 15px 20px;
        border-bottom: 1px solid #e2e8f0;
        position: relative;
    }

    .sidebar-search input {
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        padding: 8px 12px;
        font-size: 13px;
        width: 100%;
        background: #fff;
        outline: none;
    }

    /* Quick Access Menu */
    .sidebar-menu-title {
        font-size: 11px;
        font-weight: bold;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 15px 20px 5px 20px;
    }
    .sidebar-menu-list { list-style: none; padding: 0; margin: 0; }
    .sidebar-menu-item a {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 20px;
        color: #475569;
        font-size: 13.5px;
        text-decoration: none;
        transition: background 0.15s;
    }
    .sidebar-menu-item a:hover { background: #f1f5f9; color: #1e293b; }
    .sidebar-menu-item i { margin-right: 8px; color: #64748b; }

    /* Center Pane Viewport */
    .studio-viewport {
        background: #fff;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
    }

    /* Studio Landing Dashboard (Hub) */
    .hub-container { padding: 40px; }
    
    .hub-search-box {
        text-align: center;
        max-width: 700px;
        margin: 0 auto 40px auto;
    }
    .hub-search-input {
        width: 100%;
        height: 48px;
        border-radius: 8px;
        border: 1px solid #cbd5e1;
        padding: 12px 20px 12px 45px;
        font-size: 16px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        outline: none;
    }
    .hub-search-wrapper { position: relative; }
    .hub-search-icon { position: absolute; left: 16px; top: 14px; font-size: 18px; color: #94a3b8; }

    /* Autocomplete Overlay Dropdown Styling */
    .search-results-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        z-index: 1000;
        margin-top: 5px;
        max-height: 400px;
        overflow-y: auto;
        display: none;
        text-align: left;
    }
    .dropdown-section-title {
        font-size: 10px;
        font-weight: bold;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 10px 15px 5px 15px;
        background: #f8fafc;
        border-bottom: 1px solid #f1f5f9;
        margin-top: 5px;
    }
    .dropdown-section-title:first-child { margin-top: 0; }
    .search-result-row {
        display: block;
        padding: 10px 15px;
        font-size: 13.5px;
        color: #334155;
        text-decoration: none !important;
        transition: background 0.15s;
        cursor: pointer;
    }
    .search-result-row:hover { background: #eff6ff; color: #1d4ed8; }
    .search-result-row i { margin-right: 8px; width: 16px; text-align: center; color: #64748b; }

    /* Cards Grid */
    .hub-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    .hub-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 20px;
        text-decoration: none !important;
        transition: transform 0.15s, box-shadow 0.15s;
        color: #1e293b !important;
    }
    .hub-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
        border-color: #cbd5e1;
    }
    .hub-card-title { font-weight: bold; font-size: 15px; margin-bottom: 5px; }
    .hub-card-metric { font-size: 28px; font-weight: 800; color: #3b82f6; }

    /* Bottom Widgets layout */
    .hub-widgets {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 30px;
    }
    .widget-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 25px; }
    .widget-title { font-weight: bold; font-size: 15px; color: #475569; text-transform: uppercase; margin-bottom: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }

    /* Directory Browser Styles (Phase 2 correction additions) */
    .dir-container { padding: 40px; }
    .dir-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px; }
    .dir-card { background: #fff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 20px; transition: border-color 0.15s; }
    .dir-card:hover { border-color: #3b82f6; }
    .dir-card-title { font-weight: bold; font-size: 15px; color: #1e293b; margin-top: 0; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
    
    .hidden-templates { display: none; }
</style>
<link rel="stylesheet" href="{{ url('css/dist/store-operations.css') }}">

<div class="row">
    <div class="col-md-12">
        <div class="studio-container">
            
            <!-- COLUMN 1: STREAMLINED QUICK ACCESS SIDEBAR -->
            <div class="studio-sidebar">
                <!-- Sidebar Header: Clicking this takes you back to the home hub dashboard -->
                <div class="sidebar-header" id="btn_back_to_hub">
                    <h4 style="margin: 0; color: #0f172a; font-weight: bold;">
                        <i class="fa fa-sliders text-blue"></i> {{ __('storeops::storeops.rules_ui.rule_studio') }}
                    </h4>
                    <p class="text-muted" style="font-size: 11px; margin: 4px 0 0 0; text-transform: uppercase; letter-spacing: 0.5px;">{{ __('storeops::storeops.rules_ui.gpo_console') }}</p>
                </div>

                <!-- Sidebar Autocomplete Search Container -->
                <div class="sidebar-search">
                    <input type="text" id="sidebarSearch" placeholder="🔍 {{ __('storeops::rules.search') }}" autocomplete="off">
                    <div class="search-results-dropdown" id="sidebarDropdown"></div>
                </div>

                <!-- Quick Entry Directory Directory -->
                <div class="sidebar-menu-title">{{ __('storeops::storeops.rules_ui.directories') }}</div>
                <ul class="sidebar-menu-list">
                    <li class="sidebar-menu-item">
                        <a href="#" id="sidebar_categories_trigger">
                            <span><i class="fa fa-cubes"></i> {{ __('storeops::storeops.rules_ui.product_categories') }}</span>
                            <span class="badge bg-blue" style="border-radius: 4px;">{{ $counts['categories'] }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="#" id="sidebar_offices_trigger">
                            <span><i class="fa fa-building-o"></i> {{ __('storeops::storeops.rules_ui.offices_locations') }}</span>
                            <span class="badge bg-blue" style="border-radius: 4px;">{{ $counts['locations'] }}</span>
                        </a>
                    </li>
                </ul>

                <!-- ⭐ Visited Targets List -->
                <div class="sidebar-menu-title">{{ __('storeops::storeops.rules_ui.recently_visited') }}</div>
                <ul class="sidebar-menu-list" id="recent_targets_list">
                    <!-- Javascript populates items here in real-time -->
                </ul>
            </div>

            <!-- COLUMN 2 & 3: THE MAIN VIEWPORT (Renders Hub, Directory lists, or Inspector dynamically) -->
            <div class="studio-viewport" id="workspace_pane">
                <!-- Landing Dashboard Wrapper -->
                <div class="hub-container" id="hub_dashboard_wrapper">
                    
                    <!-- Massive Central Search Bar -->
                    <div class="hub-search-box">
                        <h2 style="font-weight: 800; color: #0f172a; margin-bottom: 25px;">{{ __('storeops::storeops.rules_ui.find_and_configure_business_rules') }}</h2>
                        <div class="hub-search-wrapper">
                            <i class="fa fa-search hub-search-icon"></i>
                            <input type="text" id="centralSearchInput" class="hub-search-input" placeholder="{{ __('storeops::rules.search') }}" autocomplete="off">
                            <div class="search-results-dropdown" id="centralDropdown" style="border-radius: 8px;"></div>
                        </div>
                    </div>

                    <!-- 1. CREATE NEW RULE SECTION (The Visual Cards Portal) -->
                    <h4 style="font-weight: bold; color: #475569; text-transform: uppercase; margin-bottom: 15px; font-size:12.5px; letter-spacing:0.5px;">{{ __('storeops::storeops.rules_ui.create_new_business_rule') }}</h4>
                    <div class="hub-grid" style="margin-bottom: 40px;">
                        <a href="{{ route('storeops.admin.rules.policies.create', 'hardware') }}" class="hub-card" style="border-left: 4px solid #3b82f6;">
                            <div class="hub-card-title"><i class="fa fa-laptop text-blue"></i> {{ __('storeops::storeops.rules_ui.hardware_standard') }}</div>
                            <small class="text-muted" style="display: block; margin-top: 5px; line-height: 1.4;">
                                {{ __('storeops::storeops.rules_ui.pre_configures_unique_serial_number_tracking_and_automatic_indiv') }}
                            </small>
                            <div style="margin-top: 15px; font-weight: bold; color: #3b82f6; font-size:13px;">{{ __('storeops::storeops.rules_ui.use_template') }}</div>
                        </a>
                        <a href="{{ route('storeops.admin.rules.policies.create', 'consumable') }}" class="hub-card" style="border-left: 4px solid #10b981;">
                            <div class="hub-card-title"><i class="fa fa-tint text-green"></i> {{ __('storeops::storeops.rules_ui.consumable_standard') }}</div>
                            <small class="text-muted" style="display: block; margin-top: 5px; line-height: 1.4;">
                                {{ __('storeops::storeops.rules_ui.pre_configures_bulk_quantity_entries_and_direct_ledger_card_post') }}
                            </small>
                            <div style="margin-top: 15px; font-weight: bold; color: #10b981; font-size:13px;">{{ __('storeops::storeops.rules_ui.use_template') }}</div>
                        </a>
                        <a href="{{ route('storeops.admin.rules.policies.create', 'blank') }}" class="hub-card" style="border-left: 4px solid #64748b;">
                            <div class="hub-card-title"><i class="fa fa-file-text-o text-muted"></i> {{ __('storeops::storeops.rules_ui.blank_rule_set') }}</div>
                            <small class="text-muted" style="display: block; margin-top: 5px; line-height: 1.4;">
                                {{ __('storeops::storeops.rules_ui.start_completely_from_scratch_with_all_toggles_set_to_inherit_fr') }}
                            </small>
                            <div style="margin-top: 15px; font-weight: bold; color: #64748b; font-size:13px;">{{ __('storeops::storeops.rules_ui.start_blank') }}</div>
                        </a>
                    </div>

                    <!-- 2. EXISTING LAUNCHED RULES LIBRARY TABLE -->
                    <h4 style="font-weight: bold; color: #475569; text-transform: uppercase; margin-bottom: 15px; font-size:12.5px; letter-spacing:0.5px;">{{ __('storeops::storeops.rules_ui.existing_policy_files') }}</h4>
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px 25px; margin-bottom: 40px;">
                        <table class="table table-hover" style="margin-bottom: 0;">
                            <thead>
                                <tr style="color: #64748b; font-size:12px; text-transform: uppercase;">
                                    <th>{{ __('storeops::storeops.rules_ui.policy_name') }}</th>
                                    <th>{{ __('storeops::storeops.rules_ui.status') }}</th>
                                    <th>{{ __('storeops::storeops.rules_ui.version') }}</th>
                                    <th class="text-right">{{ __('storeops::storeops.rules_ui.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($publishedProfiles as $profile)
                                    <tr>
                                        <td style="vertical-align: middle;"><strong>{{ $profile->name }}</strong></td>
                                        <td style="vertical-align: middle;">
                                            <span class="label label-success" style="border-radius:4px;">{{ $profile->status->value }}</span>
                                        </td>
                                        <td style="vertical-align: middle;">v{{ $profile->version ?? '1.0' }}</td>
                                        <td class="text-right" style="vertical-align: middle;">
                                            <a href="{{ route('storeops.admin.rules.policies.edit', $profile->id) }}" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i> {{ __('storeops::storeops.rules_ui.open_builder') }}</a>
                                            
                                            <form action="{{ route('storeops.admin.rules.policies.duplicate', $profile->id) }}" method="POST" style="display:inline;">
                                                @csrf
                                                <button type="submit" class="btn btn-xs btn-default"><i class="fa fa-copy"></i> {{ __('storeops::storeops.rules_ui.duplicate') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Widgets Section -->
                    <div class="hub-widgets">
                        <div class="widget-panel">
                            <div class="widget-title"><i class="fa fa-history"></i> {{ __('storeops::storeops.rules_ui.recent_gpo_alignment_changes') }}</div>
                            <ul class="timeline timeline-inverse" style="margin-top: 10px; margin-bottom: 0;">
                                @forelse($recentActivity as $act)
                                    <li>
                                        <i class="fa fa-check bg-green"></i>
                                        <div class="timeline-item" style="box-shadow:none; background:#f8fafc; border: 1px solid #e2e8f0; border-radius: 4px;">
                                            <span class="time"><i class="fa fa-clock-o"></i> {{ $act['date'] }}</span>
                                            <h4 class="timeline-header" style="border:none; font-size:13.5px; padding-bottom:0;">
                                                {{ __('storeops::storeops.rules_ui.policy') }} <strong>{{ $act['policy_name'] }}</strong> {{ __('storeops::storeops.rules_ui.assigned_to') }} <strong>{{ $act['target_name'] }}</strong>
                                            </h4>
                                            <div class="timeline-body" style="padding-top:2px; font-size:12px; color:#64748b;">
                                                Modified by {{ $act['operator'] }}
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-muted" style="font-size: 13px; padding-left: 15px;">{{ __('storeops::storeops.rules_ui.no_recent_assignment_changes_recorded') }}</li>
                                @endforelse
                                <li><i class="fa fa-clock-o bg-gray"></i></li>
                            </ul>
                        </div>

                        <div class="widget-panel">
                            <div class="widget-title"><i class="fa fa-rocket"></i> {{ __('storeops::storeops.rules_ui.actions') }}</div>
                            <button class="btn btn-default btn-block text-left" style="margin-bottom:12px; padding: 10px 15px;" onclick="window.location.href='{{ route('storeops.admin.rules.simulator') }}'">
                                <i class="fa fa-flask text-blue" style="margin-right: 8px;"></i> {{ __('storeops::storeops.rules_ui.launch_policy_simulator') }}
                            </button>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- HIDDEN BROWSER TEMPLATES (Used to instantly render clean cards lists) -->
<!-- ======================================================================= -->
<div class="hidden-templates">
    
    <!-- A. PRODUCT CATEGORIES DIRECTORY LIST -->
    <div id="portal_categories_dir">
        <div class="dir-container">
            <h3 style="margin-top:0; font-weight:800; color:#0f172a;"><i class="fa fa-cubes text-blue"></i> {{ __('storeops::storeops.rules_ui.browse_product_categories') }}</h3>
            <p class="text-muted">{{ __('storeops::storeops.rules_ui.select_a_category_below_to_inspect_its_inherited_and_localized_b') }}</p>
            
            <div class="dir-grid">
                @foreach($tree as $group => $items)
                    @foreach($items as $item)
                        @if($item['type'] === 'CATEGORY')
                            <div class="dir-card">
                                <div class="dir-card-title"><i class="fa {{ $item['icon'] }} text-blue"></i> {{ $item['name'] }}</div>
                                <button class="btn btn-sm btn-primary btn-block direct-inspect-btn" data-id="{{ $item['id'] }}" data-type="{{ $item['type'] }}" data-name="{{ $item['name'] }}">
                                    <i class="fa fa-search"></i> {{ __('storeops::storeops.rules_ui.inspect_rules') }}
                                </button>
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>

    <!-- B. OFFICES / LOCATIONS DIRECTORY LIST -->
    <div id="portal_offices_dir">
        <div class="dir-container">
            <h3 style="margin-top:0; font-weight:800; color:#0f172a;"><i class="fa fa-building-o text-green"></i> {{ __('storeops::storeops.rules_ui.browse_scoped_offices') }}</h3>
            <p class="text-muted">{{ __('storeops::storeops.rules_ui.select_a_localized_office_below_to_inspect_or_configure_localize') }}</p>
            
            <div class="dir-grid">
                @foreach($tree as $group => $items)
                    @foreach($items as $item)
                        @if($item['type'] === 'LOCATION')
                            <div class="dir-card">
                                <div class="dir-card-title"><i class="fa {{ $item['icon'] }} text-green"></i> {{ $item['name'] }}</div>
                                <button class="btn btn-sm btn-success btn-block direct-inspect-btn" data-id="{{ $item['id'] }}" data-type="{{ $item['type'] }}" data-name="{{ $item['name'] }}">
                                    <i class="fa fa-search"></i> {{ __('storeops::storeops.rules_ui.inspect_rules') }}
                                </button>
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@section('moar_scripts')
@include('storeops::admin.rules.partials.client-config', ['rulesPage' => 'index'])
@endsection
