{{-- One tracking code's plan, scope, order document, lifecycle and status changes (Manage tab row detail). --}}
@php
    $firstReceipt = collect($c['receipts'])->pluck('date')->filter()->sort()->first();
    $scopeItems = [
        ['label' => __($m.'detail.plan'), 'value' => $planLabel],
        ['label' => __($m.'detail.area'), 'value' => $c['geography_label']],
        ['label' => __($m.'detail.participants'), 'value' => $c['participants_label']],
        ['label' => __($m.'detail.offices'), 'value' => $c['offices']],
        ['label' => __($m.'detail.fy'), 'value' => $c['fy']],
        ['label' => __($m.'detail.funding'), 'value' => $c['funding'] ?? '—'],
    ];
    // Only steps that happened: a missing date would read as "unavailable" rather than "not yet".
    $steps = array_values(array_filter([
        ['label' => __($m.'detail.created'), 'date' => $date($c['created'])],
        ['label' => __($m.'detail.activated'), 'date' => $date($c['activated'])],
        ['label' => __($m.'detail.first'), 'date' => $date($firstReceipt)],
        ['label' => __($m.'detail.archived'), 'date' => $date($c['archived_at'])],
    ], fn ($step) => $step['date'] !== null));
    $showReceived = $c['status'] !== 'DRAFT';
@endphp
<div class="gs-tracking-manage__detail">
    <div class="gs-tracking-manage__dsec">
        <h3>{{ __($m.'detail.products') }}</h3>
        @if($c['plan'] === '1_BLANKET')
            <p class="gs-tracking-muted">{{ __($m.'detail.blanket') }}</p>
        @elseif(! $c['targets'])
            <p class="gs-tracking-muted">{{ __($m.'detail.no_products') }}</p>
        @else
            <div class="gs-table-wrap gs-tracking-manage__products">
                <table class="gs-table">
                    <thead><tr><th scope="col">{{ __($m.'detail.product') }}</th><th scope="col">{{ __($m.'detail.econ') }}</th><th scope="col" class="gs-num">{{ __($m.'detail.planned') }}</th>@if($showReceived)<th scope="col" class="gs-num">{{ __($m.'detail.received') }}</th>@endif</tr></thead>
                    <tbody>
                    @foreach($c['targets'] as $target)
                        <tr><td>{{ $target['name'] }}</td><td class="gs-tracking-muted">{{ $target['economic_code'] ?: '—' }}</td><td class="gs-num">{{ $num($target['planned']) }}</td>@if($showReceived)<td class="gs-num">{{ $num($target['received']) }}</td>@endif</tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><th scope="row" colspan="2">{{ __($m.'detail.total') }}</th><td class="gs-num">{{ $num($c['planned']) }}</td>@if($showReceived)<td class="gs-num">{{ $num($c['received']) }}</td>@endif</tr></tfoot>
                </table>
            </div>
        @endif
    </div>

    <div class="gs-tracking-manage__dsec">
        <h3>{{ __($m.'detail.scope') }}</h3>
        <x-gs::key-value :items="$scopeItems" :columns="1" />
    </div>

    <div class="gs-tracking-manage__dsec" id="order-{{ $c['id'] }}">
        <h3>{{ __($m.'detail.order') }}</h3>
        @if($c['order_pdf'])
            <p><a href="{{ route('gov.tracking.tracking-codes.download', $c['id']) }}"><i class="fas fa-file-pdf" aria-hidden="true"></i> {{ __($m.'detail.order_on_file') }}</a></p>
        @else
            <p class="gs-tracking-manage__order-missing" @if($rules['documents']) data-required @endif><i class="fas fa-file-circle-exclamation" aria-hidden="true"></i> {{ __($m.'detail.order_none') }} {{ __($m.($rules['documents'] ? 'detail.order_required' : 'detail.order_optional')) }}</p>
        @endif
        @if($canAttach)
            <form method="POST" enctype="multipart/form-data" class="gs-tracking-manage__upload" action="{{ route('gov.tracking.initiatives.tracking-codes.order', [$initiative->id, $c['id']]) }}">
                @csrf
                <label for="order-file-{{ $c['id'] }}">{{ __($m.'detail.order_file') }}</label>
                <input type="file" id="order-file-{{ $c['id'] }}" name="order_pdf" accept="application/pdf" required>
                <button type="submit" class="gs-btn gs-btn--primary gs-btn--sm"><i class="fas fa-file-arrow-up" aria-hidden="true"></i> {{ __($m.'action.'.($c['order_pdf'] ? 'replace' : 'attach')) }}</button>
                @if($c['status'] === 'ACTIVE')<small class="gs-tracking-muted">{{ __($m.'detail.order_once') }}</small>@endif
            </form>
        @endif
        @if(count($c['documents']))
            <h3>{{ __($m.'detail.documents') }}</h3>
            <ul class="gs-tracking-manage__docs">
                @foreach($c['documents'] as $document)
                    <li><a href="{{ route('gov.tracking.documents.download', [$initiative->id, $c['id'], $document->id]) }}"><i class="fas fa-paperclip" aria-hidden="true"></i> {{ $document->file_name }}</a></li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="gs-tracking-manage__dsec gs-tracking-manage__dsec--wide">
        <h3>{{ __($m.'detail.life') }}</h3>
        <x-gs::lifecycle :steps="$steps" />
    </div>

    @if($can['edit'] || $c['status'] === 'ARCHIVED')
        <div class="gs-tracking-manage__dsec gs-tracking-manage__dsec--wide gs-tracking-manage__change">
            <h3>{{ __($m.'detail.change') }}</h3>
            @if($c['status'] === 'DRAFT')
                <x-gs::action-row :title="__($m.'row.activate')" :description="__($m.'row.activate_why')" :unavailable="$activateWhy" :id="'manage-activate-'.$c['id']">
                    <x-slot:actions>
                        @if($activateWhy)
                            <button type="button" class="gs-btn gs-btn--success gs-btn--sm" disabled aria-describedby="manage-activate-{{ $c['id'] }}-why"><i class="fas fa-circle-play" aria-hidden="true"></i> {{ __($m.'action.activate') }}</button>
                        @else
                            <form method="POST" action="{{ route('gov.tracking.initiatives.tracking-codes.activate', [$initiative->id, $c['id']]) }}">@csrf<button type="submit" class="gs-btn gs-btn--success gs-btn--sm" data-tracking-confirm="{{ __($m.'confirm.activate', ['code' => $c['code']]) }}"><i class="fas fa-circle-play" aria-hidden="true"></i> {{ __($m.'action.activate') }}</button></form>
                        @endif
                    </x-slot:actions>
                </x-gs::action-row>
                <x-gs::action-row :title="__($m.'row.delete')" :description="__($m.'row.delete_why')" :id="'manage-delete-'.$c['id']">
                    <x-slot:actions>
                        <form method="POST" action="{{ route('gov.tracking.initiatives.tracking-codes.destroy', [$initiative->id, $c['id']]) }}">@csrf @method('DELETE')<button type="submit" class="gs-btn gs-btn--danger gs-btn--outline gs-btn--sm" data-tracking-confirm="{{ __($m.'confirm.delete', ['code' => $c['code']]) }}"><i class="fas fa-trash" aria-hidden="true"></i> {{ __($m.'action.delete') }}</button></form>
                    </x-slot:actions>
                </x-gs::action-row>
            @elseif($c['status'] === 'ACTIVE')
                <x-gs::action-row :title="__($m.'row.archive')" :description="__($m.'row.archive_why')" :unavailable="$can['head'] ? null : __($m.'lock_head')" :id="'manage-archive-'.$c['id']">
                    <x-slot:actions>
                        @if($can['head'])
                            <form method="POST" action="{{ route('gov.tracking.initiatives.tracking-codes.archive', [$initiative->id, $c['id']]) }}">@csrf<button type="submit" class="gs-btn gs-btn--danger gs-btn--outline gs-btn--sm" data-tracking-confirm="{{ __($m.'confirm.archive', ['code' => $c['code']]) }}"><i class="fas fa-box-archive" aria-hidden="true"></i> {{ __($m.'action.archive') }}</button></form>
                        @else
                            <button type="button" class="gs-btn gs-btn--danger gs-btn--outline gs-btn--sm" disabled aria-describedby="manage-archive-{{ $c['id'] }}-why"><i class="fas fa-box-archive" aria-hidden="true"></i> {{ __($m.'action.archive') }}</button>
                        @endif
                    </x-slot:actions>
                </x-gs::action-row>
            @else
                <p class="gs-tracking-muted"><i class="fas fa-lock" aria-hidden="true"></i> {{ __($m.'row.archived_ro') }}</p>
            @endif
        </div>
    @endif
</div>
