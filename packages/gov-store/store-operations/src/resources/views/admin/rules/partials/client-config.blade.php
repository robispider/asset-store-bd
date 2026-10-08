@php
    $rulesConfig = [
        'page' => $rulesPage,
        'labels' => __('storeops::storeops.rules_js'),
        'urls' => [
            'search' => route('storeops.admin.rules.search_api'),
            'inspector' => route('storeops.admin.rules.inspector'),
            'simulator' => route('storeops.admin.rules.simulator.run'),
            'edit' => route('storeops.admin.rules.policies.edit', '__ID__'),
            'impact' => isset($policy) ? route('storeops.admin.rules.policies.impact', $policy->id) : null,
        ],
    ];
@endphp
<script type="application/json" id="storeops-rules-config">@json($rulesConfig)</script>
<script src="{{ url('js/dist/store-operations-rules.js') }}?v={{ filemtime(public_path('js/dist/store-operations-rules.js')) }}" defer></script>
