@extends('layouts/default')
@section('title', __('storeops::rules.studio'))

@section('content')
<div class="storeops-theme">

<link rel="stylesheet" href="{{ url('css/dist/store-operations.css') }}">

<div class="row">
    <div class="col-md-12">
        <div class="studio-container">

            <!-- COLUMN 1: STREAMLINED QUICK ACCESS SIDEBAR -->
            <div class="studio-sidebar">
                <!-- Sidebar Header: Clicking this takes you back to the home hub dashboard -->
                <div class="sidebar-header" id="btn_back_to_hub">
                    <h4  class="storeops-inline-76">
                        <i class="fa fa-sliders text-blue"></i> {{ __('storeops::storeops.rules_ui.rule_studio') }}
                    </h4>
                    <p class="text-muted storeops-inline-75" >{{ __('storeops::storeops.rules_ui.gpo_console') }}</p>
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
                            <span class="badge bg-blue storeops-inline-74" >{{ $counts['categories'] }}</span>
                        </a>
                    </li>
                    <li class="sidebar-menu-item">
                        <a href="#" id="sidebar_offices_trigger">
                            <span><i class="fa fa-building-o"></i> {{ __('storeops::storeops.rules_ui.offices_locations') }}</span>
                            <span class="badge bg-blue storeops-inline-73" >{{ $counts['locations'] }}</span>
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
                        <h2  class="storeops-inline-72">{{ __('storeops::storeops.rules_ui.find_and_configure_business_rules') }}</h2>
                        <div class="hub-search-wrapper">
                            <i class="fa fa-search hub-search-icon"></i>
                            <input type="text" id="centralSearchInput" class="hub-search-input" placeholder="{{ __('storeops::rules.search') }}" autocomplete="off">
                            <div class="search-results-dropdown storeops-inline-71" id="centralDropdown" ></div>
                        </div>
                    </div>

                    <!-- 1. CREATE NEW RULE SECTION (The Visual Cards Portal) -->
                    <h4  class="storeops-inline-70">{{ __('storeops::storeops.rules_ui.create_new_business_rule') }}</h4>
                    <div class="hub-grid storeops-inline-69" >
                        <a href="{{ route('storeops.admin.rules.policies.create', 'hardware') }}" class="hub-card storeops-inline-68" >
                            <div class="hub-card-title"><i class="fa fa-laptop text-blue"></i> {{ __('storeops::storeops.rules_ui.hardware_standard') }}</div>
                            <small class="text-muted storeops-inline-67" >
                                {{ __('storeops::storeops.rules_ui.pre_configures_unique_serial_number_tracking_and_automatic_indiv') }}
                            </small>
                            <div  class="storeops-inline-66">{{ __('storeops::storeops.rules_ui.use_template') }}</div>
                        </a>
                        <a href="{{ route('storeops.admin.rules.policies.create', 'consumable') }}" class="hub-card storeops-inline-65" >
                            <div class="hub-card-title"><i class="fa fa-tint text-green"></i> {{ __('storeops::storeops.rules_ui.consumable_standard') }}</div>
                            <small class="text-muted storeops-inline-64" >
                                {{ __('storeops::storeops.rules_ui.pre_configures_bulk_quantity_entries_and_direct_ledger_card_post') }}
                            </small>
                            <div  class="storeops-inline-63">{{ __('storeops::storeops.rules_ui.use_template') }}</div>
                        </a>
                        <a href="{{ route('storeops.admin.rules.policies.create', 'blank') }}" class="hub-card storeops-inline-62" >
                            <div class="hub-card-title"><i class="fa fa-file-text-o text-muted"></i> {{ __('storeops::storeops.rules_ui.blank_rule_set') }}</div>
                            <small class="text-muted storeops-inline-61" >
                                {{ __('storeops::storeops.rules_ui.start_completely_from_scratch_with_all_toggles_set_to_inherit_fr') }}
                            </small>
                            <div  class="storeops-inline-60">{{ __('storeops::storeops.rules_ui.start_blank') }}</div>
                        </a>
                    </div>

                    <!-- 2. EXISTING LAUNCHED RULES LIBRARY TABLE -->
                    <h4  class="storeops-inline-59">{{ __('storeops::storeops.rules_ui.existing_policy_files') }}</h4>
                    <div  class="storeops-inline-58">
                        <table class="table table-hover storeops-inline-57 gs-table" >
                            <thead>
                                <tr  class="storeops-inline-56">
                                    <th>{{ __('storeops::storeops.rules_ui.policy_name') }}</th>
                                    <th>{{ __('storeops::storeops.rules_ui.status') }}</th>
                                    <th>{{ __('storeops::storeops.rules_ui.version') }}</th>
                                    <th class="text-right">{{ __('storeops::storeops.rules_ui.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($publishedProfiles as $profile)
                                    <tr>
                                        <td  class="storeops-inline-55"><strong>{{ $profile->name }}</strong></td>
                                        <td  class="storeops-inline-54">
                                            <span class="label label-success storeops-inline-53" >{{ $profile->status->value }}</span>
                                        </td>
                                        <td  class="storeops-inline-52">v{{ $profile->version ?? '1.0' }}</td>
                                        <td class="text-right storeops-inline-51" >
                                            <a href="{{ route('storeops.admin.rules.policies.edit', $profile->id) }}" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i> {{ __('storeops::storeops.rules_ui.open_builder') }}</a>

                                            <form action="{{ route('storeops.admin.rules.policies.duplicate', $profile->id) }}" method="POST"  class="storeops-inline-50">
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
                            <ul class="timeline timeline-inverse storeops-inline-49" >
                                @forelse($recentActivity as $act)
                                    <li>
                                        <i class="fa fa-check bg-green"></i>
                                        <div class="timeline-item storeops-inline-48" >
                                            <span class="time"><i class="fa fa-clock-o"></i> {{ $act['date'] }}</span>
                                            <h4 class="timeline-header storeops-inline-47" >
                                                {{ __('storeops::storeops.rules_ui.policy') }} <strong>{{ $act['policy_name'] }}</strong> {{ __('storeops::storeops.rules_ui.assigned_to') }} <strong>{{ $act['target_name'] }}</strong>
                                            </h4>
                                            <div class="timeline-body storeops-inline-46" >
                                                Modified by {{ $act['operator'] }}
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <li class="text-muted storeops-inline-45" >{{ __('storeops::storeops.rules_ui.no_recent_assignment_changes_recorded') }}</li>
                                @endforelse
                                <li><i class="fa fa-clock-o bg-gray"></i></li>
                            </ul>
                        </div>

                        <div class="widget-panel">
                            <div class="widget-title"><i class="fa fa-rocket"></i> {{ __('storeops::storeops.rules_ui.actions') }}</div>
                            <button class="btn btn-default btn-block text-left storeops-inline-44"  onclick="window.location.href='{{ route('storeops.admin.rules.simulator') }}'">
                                <i class="fa fa-flask text-blue storeops-inline-43" ></i> {{ __('storeops::storeops.rules_ui.launch_policy_simulator') }}
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
            <h3  class="storeops-inline-42"><i class="fa fa-cubes text-blue"></i> {{ __('storeops::storeops.rules_ui.browse_product_categories') }}</h3>
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
            <h3  class="storeops-inline-41"><i class="fa fa-building-o text-green"></i> {{ __('storeops::storeops.rules_ui.browse_scoped_offices') }}</h3>
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
</div>
@endsection

@section('moar_scripts')
@include('storeops::admin.rules.partials.client-config', ['rulesPage' => 'index'])
@endsection
