<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $document->getDocumentNumber() }} - {{ __('storeops::storeops.official_record') }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px; color: #333; line-height: 1.4; }
        .container { width: 100%; max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .doc-title { font-size: 22px; font-weight: bold; text-transform: uppercase; margin: 0; }
        .doc-meta { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .meta-box { width: 48%; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .signatures { display: flex; justify-content: space-between; margin-top: 70px; }
        .sig-line { width: 200px; border-top: 1px solid #000; text-align: center; padding-top: 5px; font-weight: bold; }
        .audit-trail { font-size: 10px; color: #666; margin-top: 40px; border-top: 1px dotted #ccc; padding-top: 10px; }
        
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print();">
    <div class="container">
        
        <div class="no-print" style="margin-bottom: 20px; text-align: right;">
            <button onclick="window.print();" style="padding: 8px 16px; cursor: pointer;">{{ __('storeops::storeops.print_document') }}</button>
            <button onclick="window.close();" style="padding: 8px 16px; cursor: pointer;">{{ __('storeops::storeops.close') }}</button>
        </div>

        <div class="header">
            @if($document->status !== 'POSTED')
            <p style="font-size: 20px; font-weight: bold;">{{ trans('tenantops::access.draft_print', [], 'bn-BD') }}<br>{{ trans('tenantops::access.draft_print', [], 'en-US') }}</p>
            @endif
            <h1 class="doc-title">
                {{ __('storeops::storeops.document_types.'.$type) }}
            </h1>
            <p style="margin: 3px 0; font-weight: bold;">{{ __('storeops::storeops.government') }}</p>
            <p style="margin: 0; font-size: 11px;">{{ __('storeops::storeops.subsystem') }}</p>
        </div>

        <div class="doc-meta">
            <div class="meta-box">
                <strong>{{ __('storeops::storeops.document_no') }}:</strong> {{ $document->getDocumentNumber() }}<br>
                <strong>{{ __('storeops::storeops.date_posted') }}</strong> {{ $document->posted_at ?? '—' }}<br>
                <strong>{{ __('tenantops::access.drafted_by') }}:</strong> {{ $document->drafter?->getFullNameAttribute() ?? $document->creator?->getFullNameAttribute() ?? '—' }}<br>
                <strong>{{ __('tenantops::access.posted_by') }}:</strong> {{ $document->poster?->getFullNameAttribute() ?? '—' }}
            </div>
            <div class="meta-box" style="text-align: right;">
                <strong>{{ __('storeops::storeops.supplier') }}:</strong> {{ $document->supplier?->name ?? '—' }}<br><strong>{{ __('storeops::storeops.source') }}:</strong> {{ $document->purchase_type ? __('storeops::storeops.receiving_sources.'.$document->purchase_type) : __('storeops::storeops.standard') }}<br>
                <strong>{{ __('storeops::storeops.reference_label') }}</strong> {{ $document->references->map(fn ($ref) => $ref->reference_number)->implode(' / ') ?: '—' }}<br>
                <strong>{{ __('storeops::storeops.reference_date') }}</strong> {{ $document->reference_date ?? __('storeops::storeops.not_available') }}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">SL</th>
                    <th style="width: 50%;">{{ __('storeops::storeops.item_description') }}</th>
                    <th style="width: 15%; text-align: center;">{{ __('storeops::storeops.quantity') }}</th>
                    <th style="width: 15%; text-align: right;">{{ __('storeops::storeops.unit_cost') }}</th>
                    <th style="width: 15%; text-align: right;">{{ __('storeops::storeops.total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($document->items as $index => $item)
                <tr>
                    <td style="text-align: center;">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $item->product_name }}</strong><br>
                        <small style="color: #555;">Type: {{ ucfirst($item->product_type) }}</small>
                    </td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">{{ number_format($item->unit_cost ?? 0, 2) }}</td>
                    <td style="text-align: right;">{{ number_format(($item->quantity * ($item->unit_cost ?? 0)), 2) }}</td>
                </tr>
                @endforeach
                <tr style="background-color: #fafafa;">
                    <td colspan="2" style="text-align: right;"><strong>{{ __('storeops::storeops.total_label') }}</strong></td>
                    <td style="text-align: center;"><strong>{{ $document->items->sum('quantity') }}</strong></td>
                    <td></td>
                    <td style="text-align: right;">
                        <strong>{{ number_format($document->items->sum(fn($i) => $i->quantity * ($i->unit_cost ?? 0)), 2) }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Supporting Files Summary -->
        <p style="font-size: 11px; color: #555;">
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
