{{-- task-form.js shows the panel that matches the chosen strategy; the inline display is its initial state. --}}
<x-gs::box id="panel-level3" icon="fas fa-table" :title="__('govtracking::general.matrix.title')" style="display: none;">
        <x-gs::alert tone="info" :title="__('govtracking::general.matrix.help_title')">
            <ul class="gs-tracking-list">
                <li>{{ __('govtracking::general.matrix.help_menu') }}</li>
                <li>{{ __('govtracking::general.matrix.help_keys') }}</li>
                <li>{{ __('govtracking::general.matrix.help_paste') }}</li>
                <li>{{ __('govtracking::general.matrix.help_drag') }}</li>
            </ul>
        </x-gs::alert>

        <!-- Dynamic Spreadsheet Real-Time Status Bar -->
        <div id="matrix-status-bar" class="gs-matrix-status-bar">
            <span id="matrix-status-text" role="status" aria-live="polite">
                <span class="gs-tracking-text-success"><i class="fas fa-circle-check" aria-hidden="true"></i> {{ __('govtracking::general.matrix.healthy') }}</span>
            </span>
        </div>

        <!-- The Spreadsheet Container -->
        <div class="gs-grid-container">
            <table class="table" id="matrix-grid-table">
                <thead>
                    <!-- Rendered dynamically by the State Engine -->
                </thead>
                <tbody id="matrix-grid-body">
                    <!-- Rendered dynamically by the State Engine -->
                </tbody>
                <tfoot>
                    <!-- Rendered dynamically by the State Engine -->
                </tfoot>
            </table>
        </div>

        <!-- Hidden serialization container populated before submit -->
        <div id="matrix-hidden-inputs"></div>
        @foreach(['col' => ['left', 'right', 'change', 'delete'], 'row' => ['up', 'down', 'change', 'delete']] as $axis => $actions)
            <div id="{{ $axis }}-context-menu" class="gs-context-menu">
                <ul>
                    @foreach($actions as $action)
                        <li><button type="button" id="menu-opt-{{ $axis }}-{{ $action }}">{{ __('govtracking::general.matrix.' . $action) }}</button></li>
                    @endforeach
                </ul>
            </div>
        @endforeach
</x-gs::box>

@php
    $matrixData = [
        'initiativeId' => $initiative->id,
        'labels' => __('govtracking::general.matrix'),
        'searchOfficesUrl' => route('gov.tracking.api.search-offices'),
        'categories' => $categories->map(fn($c) => ['id' => $c->id, 'text' => $c->name])->values(),
        'savedCategories' => isset($trackingCode) && $trackingCode->specificity_level === '3_MATRIX'
            ? $trackingCode->targets->map(fn($t) => ['id' => $t->category_id, 'name' => $t->category?->name ?? '', 'econ' => $t->economic_code])->values() : [],
        'savedLocations' => isset($trackingCode) && $trackingCode->specificity_level === '3_MATRIX'
            ? $trackingCode->targets->flatMap->allocations->map(fn($a) => ['id' => $a->location_id, 'name' => $a->location?->name ?? ''])->unique('id')->values() : [],
        'savedValues' => $savedMatrixValues ?? [],
    ];
@endphp
<script type="application/json" id="tracking-matrix-data">@json($matrixData)</script>
