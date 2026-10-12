<!DOCTYPE html>
@php($gsPrintAttributes = app()->bound('gs.theme') ? ' '.app('gs.theme')->htmlAttributes() : '')
<html lang="{{ app()->getLocale() }}" {!! $gsPrintAttributes !!}>
<head>
    <meta charset="UTF-8">
    <title>{{ $document->getDocumentNumber() }} - {{ __('storeops::storeops.official_record') }}</title>
    @if (view()->exists('gs-theme::head'))
        @include('gs-theme::head')
    @endif

</head>
<body class="storeops-print" onload="window.print();">
    <div class="container">

        <div class="no-print storeops-inline-182" >
            <button onclick="window.print();"  class="storeops-inline-181">{{ __('storeops::storeops.print_document') }}</button>
            <button onclick="window.close();"  class="storeops-inline-180">{{ __('storeops::storeops.close') }}</button>
        </div>

        <div class="header">
            @if($document->status !== 'POSTED')
            <p  class="storeops-inline-179">{{ trans('tenantops::access.draft_print', [], 'bn-BD') }}<br>{{ trans('tenantops::access.draft_print', [], 'en-US') }}</p>
            @endif
            <h1 class="doc-title">
                {{ __('storeops::storeops.document_types.'.$type) }}
            </h1>
            <p  class="storeops-inline-178">{{ __('storeops::storeops.government') }}</p>
            <p  class="storeops-inline-177">{{ __('storeops::storeops.subsystem') }}</p>
        </div>

        <div class="doc-meta">
            <div class="meta-box">
                <strong>{{ __('storeops::storeops.document_no') }}:</strong> {{ $document->getDocumentNumber() }}<br>
                <strong>{{ __('storeops::storeops.date_posted') }}</strong> {{ $document->posted_at ?? '—' }}<br>
                <strong>{{ __('tenantops::access.drafted_by') }}:</strong> {{ $document->drafter?->getFullNameAttribute() ?? $document->creator?->getFullNameAttribute() ?? '—' }}<br>
                <strong>{{ __('tenantops::access.posted_by') }}:</strong> {{ $document->poster?->getFullNameAttribute() ?? '—' }}
            </div>
            <div class="meta-box storeops-inline-176" >
                <strong>{{ __('storeops::storeops.supplier') }}:</strong> {{ $document->supplier?->name ?? '—' }}<br><strong>{{ __('storeops::storeops.source') }}:</strong> {{ $document->purchase_type ? __('storeops::storeops.receiving_sources.'.$document->purchase_type) : __('storeops::storeops.standard') }}<br>
                <strong>{{ __('storeops::storeops.reference_label') }}</strong> {{ $document->references->map(fn ($ref) => $ref->reference_number)->implode(' / ') ?: '—' }}<br>
                <strong>{{ __('storeops::storeops.reference_date') }}</strong> {{ $document->reference_date ?? __('storeops::storeops.not_available') }}
            </div>
        </div>

        <table class="gs-table">
            <thead>
                <tr>
                    <th  class="storeops-inline-175">SL</th>
                    <th  class="storeops-inline-174">{{ __('storeops::storeops.item_description') }}</th>
                    <th  class="storeops-inline-173">{{ __('storeops::storeops.quantity') }}</th>
                    <th  class="storeops-inline-172">{{ __('storeops::storeops.unit_cost') }}</th>
                    <th  class="storeops-inline-171">{{ __('storeops::storeops.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($document->items as $index => $item)
                <tr>
                    <td  class="storeops-inline-170">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->product_name }}</strong><br>
                        <small  class="storeops-inline-169">Type: {{ ucfirst($item->product_type) }}</small>
                    </td>
                    <td  class="storeops-inline-168">{{ $item->quantity }}</td>
                    <td  class="storeops-inline-167">{{ number_format($item->unit_cost ?? 0, 2) }}</td>
                    <td  class="storeops-inline-166">{{ number_format(($item->quantity * ($item->unit_cost ?? 0)), 2) }}</td>
                </tr>
                @endforeach
                <tr  class="storeops-inline-165">
                    <td colspan="2"  class="storeops-inline-164"><strong>{{ __('storeops::storeops.total_label') }}</strong></td>
                    <td  class="storeops-inline-163"><strong>{{ $document->items->sum('quantity') }}</strong></td>
                    <td></td>
                    <td  class="storeops-inline-162">
                        <strong>{{ number_format($document->items->sum(fn($i) => $i->quantity * ($i->unit_cost ?? 0)), 2) }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Supporting Files Summary -->
        <p  class="storeops-inline-161">
            <strong>Attached Supporting Scans:</strong> {{ $document->attachments->count() }} Files Attached
        </p>

        <div class="signatures">
            <div class="sig-line">{{ __('storeops::storeops.prepared_by') }}</div>

            <div class="sig-line">{{ __('storeops::storeops.posted_by_signature') }}</div>
        </div>

        <div class="audit-trail">
            <strong>{{ __('storeops::storeops.audit_stamp') }}</strong> {{ $document->status === 'POSTED' ? __('storeops::storeops.ledger_posted') : __('tenantops::access.draft_print') }} <br>
            UUID: {{ $document->id }} | Hash: {{ sha1($document->id . $document->created_at) }}
        </div>
    </div>
</body>
</html>
