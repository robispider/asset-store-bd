@php
    $workspaceConfig = [
        'isDraft' => $isDraft, 'status' => $document->status, 'type' => $type, 'ledgerOpen' => $ledgerOpen,
        'id' => $document->id, 'office' => $document->location_id,
        'items' => $document->items->map(fn ($item) => [
            'product_type' => $item->product_type, 'product_id' => $item->product_id,
            'product_name' => $item->product_name, 'current_stock' => $item->current_stock,
            'quantity' => $item->quantity, 'unit_cost' => $item->unit_cost,
        ])->values()->all(),
        'urls' => [
            'draft' => route('storeops.documents.draft', ['type' => $type, 'id' => $document->id]),
            'preview' => route('storeops.documents.preview', ['type' => $type, 'id' => $document->id]),
            'metadata' => route('storeops.documents.render_meta'),
            'search' => route('storeops.api.products.search'),
            'upload' => route('storeops.documents.attachments.upload', ['type' => $type, 'id' => $document->id]),
            'tracking' => url('/gov-store/api/tracking/verify-code'),
        ],
        'labels' => trans('storeops::storeops.workspace_js'),
    ];
@endphp
<script id="storeops-config" type="application/json">@json($workspaceConfig)</script>
<script src="{{ url('js/dist/store-operations.js') }}?v={{ filemtime(public_path('js/dist/store-operations.js')) }}" defer></script>
