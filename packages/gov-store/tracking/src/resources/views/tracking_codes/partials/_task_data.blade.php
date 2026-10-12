@php
    $taskData = [
        'initiativeId' => $initiative->id,
        'editing' => isset($trackingCode),
        'specificity' => $trackingCode?->specificity_level ?? old('specificity_level', '1_BLANKET'),
        'targetCount' => isset($trackingCode) ? $trackingCode->targets->count() : count(old('targets', [[]])),
        'categories' => $categories->map(fn($c) => ['id' => $c->id, 'text' => $c->name])->values(),
        'uniquenessUrl' => route('gov.tracking.tracking-codes.check-uniqueness'),
        'labels' => __('govtracking::general.task'),
    ];
@endphp
<script type="application/json" id="tracking-task-data">@json($taskData)</script>
