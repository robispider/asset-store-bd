<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $document->getDocumentNumber() }} - Official Government Record</title>
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
            <button onclick="window.print();" style="padding: 8px 16px; cursor: pointer;">Print Document</button>
            <button onclick="window.close();" style="padding: 8px 16px; cursor: pointer;">Close</button>
        </div>

        <div class="header">
            <h1 class="doc-title">
                {{ strtoupper($type) }} NOTE
            </h1>
            <p style="margin: 3px 0; font-weight: bold;">Government of the People's Republic of Bangladesh</p>
            <p style="margin: 0; font-size: 11px;">Store Operations & Asset Management Subsystem</p>
        </div>

        <div class="doc-meta">
            <div class="meta-box">
                <strong>Document No:</strong> {{ $document->getDocumentNumber() }}<br>
                <strong>Date Posted:</strong> {{ \Carbon\Carbon::parse($document->updated_at)->format('d F Y, h:i A') }}<br>
                <strong>Operator:</strong> {{ $document->creator->first_name ?? 'System' }}
            </div>
            <div class="meta-box" style="text-align: right;">
                <strong>Source:</strong> {{ $document->purchase_type ?? 'Standard' }}<br>
                <strong>Challan / Nothi No:</strong> {{ $document->reference_no ?? 'N/A' }}<br>
                <strong>Reference Date:</strong> {{ $document->reference_date ?? 'N/A' }}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">SL</th>
                    <th style="width: 50%;">Item Description</th>
                    <th style="width: 15%; text-align: center;">Quantity</th>
                    <th style="width: 15%; text-align: right;">Unit Cost (৳)</th>
                    <th style="width: 15%; text-align: right;">Total (৳)</th>
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
                    <td colspan="2" style="text-align: right;"><strong>Total:</strong></td>
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
            <div class="sig-line">Prepared By (Storekeeper)</div>
            <div class="sig-line">Verified By</div>
            <div class="sig-line">Approved By</div>
        </div>

        <div class="audit-trail">
            <strong>System Audit Stamp:</strong> Document posted securely to Gov-Store Immutable Ledger. <br>
            UUID: {{ $document->id }} | Hash: {{ sha1($document->id . $document->created_at) }}
        </div>
    </div>
</body>
</html>